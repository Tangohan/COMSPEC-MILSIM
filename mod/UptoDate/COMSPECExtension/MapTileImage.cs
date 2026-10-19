using System;
using System.IO;
using System.IO.Compression;

namespace COMSPECExtension;

/// <summary>
/// Tuiles de la capture de carte : lecture du PNG du moteur, correction de couleur, écriture en JPEG (sans bibliothèque :
/// la DLL est compilée en natif). PNG 8 bits RGB / RGBA non entrelacé (ce qu'écrit Arma) ; JPEG de base 4:2:0.
/// </summary>
internal static class MapTileImage
{
    /// <summary>Pixels RGB (3 octets par point) ; null si le PNG n'est pas d'un format lu ici. Arma écrit du RGBA
    /// 16 bits par canal (d'où 20 Mo par image) : l'octet fort de chaque canal est gardé.</summary>
    public static byte[]? ReadPng(string path, out int width, out int height)
    {
        width = height = 0;
        var data = File.ReadAllBytes(path);
        if (data.Length < 33 || data[0] != 0x89 || data[1] != (byte)'P') return null;
        int colorType = -1, bpp = 0, depth = 8;
        using var idat = new MemoryStream();
        var pos = 8;
        while (pos + 8 <= data.Length)
        {
            var len = (data[pos] << 24) | (data[pos + 1] << 16) | (data[pos + 2] << 8) | data[pos + 3];
            var type = System.Text.Encoding.ASCII.GetString(data, pos + 4, 4);
            var body = pos + 8;
            if (len < 0 || body + len > data.Length) return null;
            if (type == "IHDR")
            {
                width = (data[body] << 24) | (data[body + 1] << 16) | (data[body + 2] << 8) | data[body + 3];
                height = (data[body + 4] << 24) | (data[body + 5] << 16) | (data[body + 6] << 8) | data[body + 7];
                depth = data[body + 8];
                if ((depth != 8 && depth != 16) || data[body + 12] != 0) return null;
                colorType = data[body + 9];
                bpp = (colorType switch { 2 => 3, 6 => 4, _ => 0 }) * depth / 8;
                if (bpp == 0 || width <= 0 || height <= 0 || width > 16384 || height > 16384) return null;
            }
            else if (type == "IDAT") idat.Write(data, body, len);
            else if (type == "IEND") break;
            pos = body + len + 4;
        }
        if (bpp == 0) return null;
        var stride = width * bpp;
        var raw = new byte[(stride + 1) * height];
        idat.Position = 0;
        using (var z = new ZLibStream(idat, CompressionMode.Decompress))
        {
            var got = 0;
            while (got < raw.Length)
            {
                var n = z.Read(raw, got, raw.Length - got);
                if (n <= 0) return null;
                got += n;
            }
        }
        var img = new byte[stride * height];
        for (var y = 0; y < height; y++)
        {
            var f = raw[y * (stride + 1)];
            var src = y * (stride + 1) + 1;
            var row = y * stride;
            var prev = row - stride;
            for (var x = 0; x < stride; x++)
            {
                int a = x >= bpp ? img[row + x - bpp] : 0;
                int b = y > 0 ? img[prev + x] : 0;
                int c = x >= bpp && y > 0 ? img[prev + x - bpp] : 0;
                int v = raw[src + x];
                switch (f)
                {
                    case 1: v += a; break;
                    case 2: v += b; break;
                    case 3: v += (a + b) >> 1; break;
                    case 4:
                        var p = a + b - c;
                        int pa = Math.Abs(p - a), pb = Math.Abs(p - b), pc = Math.Abs(p - c);
                        v += pa <= pb && pa <= pc ? a : pb <= pc ? b : c;
                        break;
                }
                img[row + x] = (byte)v;
            }
        }
        if (bpp == 3) return img;
        // Points de bpp octets : canaux de depth / 8 octets (octet fort en premier), alpha ignoré.
        var cs = depth / 8;
        var rgb = new byte[width * height * 3];
        for (int i = 0, j = 0; i < img.Length; i += bpp, j += 3) { rgb[j] = img[i]; rgb[j + 1] = img[i + cs]; rgb[j + 2] = img[i + 2 * cs]; }
        return rgb;
    }

    /// <summary>Mesures d'une tuile pour la correction : luminosité du voile de l'air (99,5 % des canaux : vue de haut, la
    /// lumière diffusée par l'air s'ajoute à tout), canal sombre (0,5 % du plus faible des trois canaux : sans voile,
    /// presque noir) et moyenne de chaque canal (balance des blancs).</summary>
    public static float[] Stats(byte[] rgb)
    {
        var hist = new int[4, 256];
        double sr = 0, sg = 0, sb = 0;
        var n = 0;
        for (var i = 0; i + 2 < rgb.Length; i += 3 * 7)
        {
            hist[0, rgb[i]]++; hist[1, rgb[i + 1]]++; hist[2, rgb[i + 2]]++;
            hist[3, Math.Min(rgb[i], Math.Min(rgb[i + 1], rgb[i + 2]))]++;
            sr += rgb[i]; sg += rgb[i + 1]; sb += rgb[i + 2];
            n++;
        }
        float air = 0;
        for (var c = 0; c < 3; c++)
        {
            int acc = 0, k = 255;
            while (k > 0 && acc + hist[c, k] < n * 0.005) acc += hist[c, k--];
            air += Math.Max(160, k) / 3f;
        }
        int a2 = 0, d = 0;
        while (d < 255 && a2 + hist[3, d] < n * 0.005) a2 += hist[3, d++];
        n = Math.Max(1, n);
        return new[] { air, d, (float)(sr / n), (float)(sg / n), (float)(sb / n) };
    }

    /// <summary>Correction d'une tuile à partir des mesures [voile, canal sombre, moyennes R, V, B] (médiane des premières
    /// tuiles d'une hauteur : les mêmes pour toutes, pas de raccord visible entre deux images voisines) :
    ///   - retire le voile, neutre (J = (I − A) / t + A, t déduit du canal sombre, borné pour ne pas tout noircir) ;
    ///   - balance des blancs douce (la moitié de l'écart à un gris moyen, dominante rose ou bleue du voile) ;
    ///   - un peu de saturation.</summary>
    public static void Grade(byte[] rgb, float[] m, float saturation)
    {
        var air = m[0];
        var t = Math.Clamp(1f - 0.9f * m[1] / air, 0.35f, 1f);
        float Dehaze(float v) => (v - air) / t + air;
        var mean = new[] { Dehaze(m[2]), Dehaze(m[3]), Dehaze(m[4]) };
        var lum = Math.Max(1f, 0.299f * mean[0] + 0.587f * mean[1] + 0.114f * mean[2]);
        var lut = new byte[3, 256];
        for (var c = 0; c < 3; c++)
        {
            var gain = Math.Clamp(MathF.Sqrt(lum / Math.Max(1f, mean[c])), 0.85f, 1.15f);
            for (var v = 0; v < 256; v++) lut[c, v] = (byte)Math.Clamp((int)(Dehaze(v) * gain + 0.5f), 0, 255);
        }
        for (var i = 0; i + 2 < rgb.Length; i += 3)
        {
            float r = lut[0, rgb[i]], g = lut[1, rgb[i + 1]], b = lut[2, rgb[i + 2]];
            var l = 0.299f * r + 0.587f * g + 0.114f * b;
            rgb[i] = (byte)Math.Clamp((int)(l + (r - l) * saturation + 0.5f), 0, 255);
            rgb[i + 1] = (byte)Math.Clamp((int)(l + (g - l) * saturation + 0.5f), 0, 255);
            rgb[i + 2] = (byte)Math.Clamp((int)(l + (b - l) * saturation + 0.5f), 0, 255);
        }
    }

    // ---------------------------------------------------------------------------------------------------------------
    // JPEG de base (Huffman standard, chrominance 4:2:0)
    // ---------------------------------------------------------------------------------------------------------------

    private static readonly int[] ZigZag =
    {
        0, 1, 8, 16, 9, 2, 3, 10, 17, 24, 32, 25, 18, 11, 4, 5, 12, 19, 26, 33, 40, 48, 41, 34, 27, 20, 13, 6, 7, 14, 21, 28,
        35, 42, 49, 56, 57, 50, 43, 36, 29, 22, 15, 23, 30, 37, 44, 51, 58, 59, 52, 45, 38, 31, 39, 46, 53, 60, 61, 54, 47, 55, 62, 63
    };
    private static readonly int[] QLum =
    {
        16, 11, 10, 16, 24, 40, 51, 61, 12, 12, 14, 19, 26, 58, 60, 55, 14, 13, 16, 24, 40, 57, 69, 56, 14, 17, 22, 29, 51, 87, 80, 62,
        18, 22, 37, 56, 68, 109, 103, 77, 24, 35, 55, 64, 81, 104, 113, 92, 49, 64, 78, 87, 103, 121, 120, 101, 72, 92, 95, 98, 112, 100, 103, 99
    };
    private static readonly int[] QChr =
    {
        17, 18, 24, 47, 99, 99, 99, 99, 18, 21, 26, 66, 99, 99, 99, 99, 24, 26, 56, 99, 99, 99, 99, 99, 47, 66, 99, 99, 99, 99, 99, 99,
        99, 99, 99, 99, 99, 99, 99, 99, 99, 99, 99, 99, 99, 99, 99, 99, 99, 99, 99, 99, 99, 99, 99, 99, 99, 99, 99, 99, 99, 99, 99, 99
    };
    private static readonly byte[] DcLumBits = { 0, 1, 5, 1, 1, 1, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0 };
    private static readonly byte[] DcChrBits = { 0, 3, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0, 0, 0 };
    private static readonly byte[] DcVals = { 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11 };
    private static readonly byte[] AcLumBits = { 0, 2, 1, 3, 3, 2, 4, 3, 5, 5, 4, 4, 0, 0, 1, 0x7d };
    private static readonly byte[] AcLumVals =
    {
        0x01, 0x02, 0x03, 0x00, 0x04, 0x11, 0x05, 0x12, 0x21, 0x31, 0x41, 0x06, 0x13, 0x51, 0x61, 0x07, 0x22, 0x71, 0x14, 0x32, 0x81, 0x91, 0xa1, 0x08,
        0x23, 0x42, 0xb1, 0xc1, 0x15, 0x52, 0xd1, 0xf0, 0x24, 0x33, 0x62, 0x72, 0x82, 0x09, 0x0a, 0x16, 0x17, 0x18, 0x19, 0x1a, 0x25, 0x26, 0x27, 0x28,
        0x29, 0x2a, 0x34, 0x35, 0x36, 0x37, 0x38, 0x39, 0x3a, 0x43, 0x44, 0x45, 0x46, 0x47, 0x48, 0x49, 0x4a, 0x53, 0x54, 0x55, 0x56, 0x57, 0x58, 0x59,
        0x5a, 0x63, 0x64, 0x65, 0x66, 0x67, 0x68, 0x69, 0x6a, 0x73, 0x74, 0x75, 0x76, 0x77, 0x78, 0x79, 0x7a, 0x83, 0x84, 0x85, 0x86, 0x87, 0x88, 0x89,
        0x8a, 0x92, 0x93, 0x94, 0x95, 0x96, 0x97, 0x98, 0x99, 0x9a, 0xa2, 0xa3, 0xa4, 0xa5, 0xa6, 0xa7, 0xa8, 0xa9, 0xaa, 0xb2, 0xb3, 0xb4, 0xb5, 0xb6,
        0xb7, 0xb8, 0xb9, 0xba, 0xc2, 0xc3, 0xc4, 0xc5, 0xc6, 0xc7, 0xc8, 0xc9, 0xca, 0xd2, 0xd3, 0xd4, 0xd5, 0xd6, 0xd7, 0xd8, 0xd9, 0xda, 0xe1, 0xe2,
        0xe3, 0xe4, 0xe5, 0xe6, 0xe7, 0xe8, 0xe9, 0xea, 0xf1, 0xf2, 0xf3, 0xf4, 0xf5, 0xf6, 0xf7, 0xf8, 0xf9, 0xfa
    };
    private static readonly byte[] AcChrBits = { 0, 2, 1, 2, 4, 4, 3, 4, 7, 5, 4, 4, 0, 1, 2, 0x77 };
    private static readonly byte[] AcChrVals =
    {
        0x00, 0x01, 0x02, 0x03, 0x11, 0x04, 0x05, 0x21, 0x31, 0x06, 0x12, 0x41, 0x51, 0x07, 0x61, 0x71, 0x13, 0x22, 0x32, 0x81, 0x08, 0x14, 0x42, 0x91,
        0xa1, 0xb1, 0xc1, 0x09, 0x23, 0x33, 0x52, 0xf0, 0x15, 0x62, 0x72, 0xd1, 0x0a, 0x16, 0x24, 0x34, 0xe1, 0x25, 0xf1, 0x17, 0x18, 0x19, 0x1a, 0x26,
        0x27, 0x28, 0x29, 0x2a, 0x35, 0x36, 0x37, 0x38, 0x39, 0x3a, 0x43, 0x44, 0x45, 0x46, 0x47, 0x48, 0x49, 0x4a, 0x53, 0x54, 0x55, 0x56, 0x57, 0x58,
        0x59, 0x5a, 0x63, 0x64, 0x65, 0x66, 0x67, 0x68, 0x69, 0x6a, 0x73, 0x74, 0x75, 0x76, 0x77, 0x78, 0x79, 0x7a, 0x82, 0x83, 0x84, 0x85, 0x86, 0x87,
        0x88, 0x89, 0x8a, 0x92, 0x93, 0x94, 0x95, 0x96, 0x97, 0x98, 0x99, 0x9a, 0xa2, 0xa3, 0xa4, 0xa5, 0xa6, 0xa7, 0xa8, 0xa9, 0xaa, 0xb2, 0xb3, 0xb4,
        0xb5, 0xb6, 0xb7, 0xb8, 0xb9, 0xba, 0xc2, 0xc3, 0xc4, 0xc5, 0xc6, 0xc7, 0xc8, 0xc9, 0xca, 0xd2, 0xd3, 0xd4, 0xd5, 0xd6, 0xd7, 0xd8, 0xd9, 0xda,
        0xe2, 0xe3, 0xe4, 0xe5, 0xe6, 0xe7, 0xe8, 0xe9, 0xea, 0xf2, 0xf3, 0xf4, 0xf5, 0xf6, 0xf7, 0xf8, 0xf9, 0xfa
    };

    private static (int[] Code, int[] Len) Huff(byte[] bits, byte[] vals)
    {
        var code = new int[256];
        var len = new int[256];
        int k = 0, c = 0;
        for (var l = 1; l <= 16; l++)
        {
            for (var i = 0; i < bits[l - 1]; i++) { code[vals[k]] = c; len[vals[k]] = l; k++; c++; }
            c <<= 1;
        }
        return (code, len);
    }

    private sealed class BitWriter
    {
        private readonly Stream _s;
        private int _acc, _n;
        public BitWriter(Stream s) { _s = s; }
        public void Put(int code, int len)
        {
            for (var i = len - 1; i >= 0; i--)
            {
                _acc = (_acc << 1) | ((code >> i) & 1);
                if (++_n == 8)
                {
                    _s.WriteByte((byte)_acc);
                    if (_acc == 0xFF) _s.WriteByte(0);
                    _acc = 0; _n = 0;
                }
            }
        }
        public void Flush() { while (_n != 0) Put(1, 1); }
    }

    private static readonly float[] Cos = BuildCos();
    private static float[] BuildCos()
    {
        var t = new float[64];
        for (var x = 0; x < 8; x++)
            for (var u = 0; u < 8; u++)
                t[x * 8 + u] = (float)(Math.Cos((2 * x + 1) * u * Math.PI / 16) * (u == 0 ? Math.Sqrt(0.125) : 0.5));
        return t;
    }

    private static void Block(float[] blk, int[] q, ref int pred, (int[] Code, int[] Len) dc, (int[] Code, int[] Len) ac, BitWriter bw)
    {
        // DCT 2D séparable, puis quantification.
        var tmp = new float[64];
        for (var y = 0; y < 8; y++)
            for (var u = 0; u < 8; u++)
            {
                float s = 0;
                for (var x = 0; x < 8; x++) s += blk[y * 8 + x] * Cos[x * 8 + u];
                tmp[y * 8 + u] = s;
            }
        var zz = new int[64];
        for (var v = 0; v < 8; v++)
            for (var u = 0; u < 8; u++)
            {
                float s = 0;
                for (var y = 0; y < 8; y++) s += tmp[y * 8 + u] * Cos[y * 8 + v];
                var idx = v * 8 + u;
                var val = (int)Math.Round(s / q[idx]);
                zz[Array.IndexOf(ZigZag, idx)] = val;
            }
        var diff = zz[0] - pred;
        pred = zz[0];
        PutValue(diff, dc, bw, 0);
        var run = 0;
        for (var i = 1; i < 64; i++)
        {
            if (zz[i] == 0) { run++; continue; }
            while (run > 15) { bw.Put(ac.Code[0xF0], ac.Len[0xF0]); run -= 16; }
            PutValue(zz[i], ac, bw, run);
            run = 0;
        }
        if (run > 0) bw.Put(ac.Code[0], ac.Len[0]);
    }

    private static void PutValue(int v, (int[] Code, int[] Len) t, BitWriter bw, int run)
    {
        var a = Math.Abs(v);
        var size = 0;
        while (a > 0) { size++; a >>= 1; }
        var sym = (run << 4) | size;
        bw.Put(t.Code[sym], t.Len[sym]);
        if (size > 0) bw.Put(v < 0 ? v + (1 << size) - 1 : v, size);
    }

    private static int[] ScaleQ(int[] q, int quality)
    {
        quality = Math.Clamp(quality, 1, 100);
        var sc = quality < 50 ? 5000 / quality : 200 - quality * 2;
        var r = new int[64];
        for (var i = 0; i < 64; i++) r[i] = Math.Clamp((q[i] * sc + 50) / 100, 1, 255);
        return r;
    }

    public static void WriteJpeg(string path, byte[] rgb, int w, int h, int quality)
    {
        var ql = ScaleQ(QLum, quality);
        var qc = ScaleQ(QChr, quality);
        var dcl = Huff(DcLumBits, DcVals); var acl = Huff(AcLumBits, AcLumVals);
        var dcc = Huff(DcChrBits, DcVals); var acc = Huff(AcChrBits, AcChrVals);
        using var fs = new FileStream(path, FileMode.Create, FileAccess.Write, FileShare.None, 1 << 16);
        void W(params byte[] b) => fs.Write(b, 0, b.Length);
        W(0xFF, 0xD8);
        W(0xFF, 0xE0, 0, 16, (byte)'J', (byte)'F', (byte)'I', (byte)'F', 0, 1, 1, 0, 0, 1, 0, 1, 0, 0);
        foreach (var (id, q) in new[] { (0, ql), (1, qc) })
        {
            W(0xFF, 0xDB, 0, 67, (byte)id);
            for (var i = 0; i < 64; i++) fs.WriteByte((byte)q[ZigZag[i]]);
        }
        W(0xFF, 0xC0, 0, 17, 8, (byte)(h >> 8), (byte)h, (byte)(w >> 8), (byte)w, 3, 1, 0x22, 0, 2, 0x11, 1, 3, 0x11, 1);
        foreach (var (cls, bits, vals) in new[] { (0x00, DcLumBits, DcVals), (0x10, AcLumBits, AcLumVals), (0x01, DcChrBits, DcVals), (0x11, AcChrBits, AcChrVals) })
        {
            var l = 19 + vals.Length;
            W(0xFF, 0xC4, (byte)(l >> 8), (byte)l, (byte)cls);
            fs.Write(bits, 0, 16);
            fs.Write(vals, 0, vals.Length);
        }
        W(0xFF, 0xDA, 0, 12, 3, 1, 0x00, 2, 0x11, 3, 0x11, 0, 63, 0);
        var bw = new BitWriter(fs);
        int py = 0, pcb = 0, pcr = 0;
        var Y = new float[4][];
        for (var i = 0; i < 4; i++) Y[i] = new float[64];
        var cb = new float[64];
        var cr = new float[64];
        for (var my = 0; my < h; my += 16)
            for (var mx = 0; mx < w; mx += 16)
            {
                Array.Clear(cb); Array.Clear(cr);
                for (var yy = 0; yy < 16; yy++)
                    for (var xx = 0; xx < 16; xx++)
                    {
                        var sx = Math.Min(mx + xx, w - 1);
                        var sy = Math.Min(my + yy, h - 1);
                        var o = (sy * w + sx) * 3;
                        float r = rgb[o], g = rgb[o + 1], b = rgb[o + 2];
                        Y[(yy >> 3) * 2 + (xx >> 3)][(yy & 7) * 8 + (xx & 7)] = 0.299f * r + 0.587f * g + 0.114f * b - 128f;
                        var ci = (yy >> 1) * 8 + (xx >> 1);
                        cb[ci] += (-0.168736f * r - 0.331264f * g + 0.5f * b) * 0.25f;
                        cr[ci] += (0.5f * r - 0.418688f * g - 0.081312f * b) * 0.25f;
                    }
                for (var i = 0; i < 4; i++) Block(Y[i], ql, ref py, dcl, acl, bw);
                Block(cb, qc, ref pcb, dcc, acc, bw);
                Block(cr, qc, ref pcr, dcc, acc, bw);
            }
        bw.Flush();
        W(0xFF, 0xD9);
    }
}
