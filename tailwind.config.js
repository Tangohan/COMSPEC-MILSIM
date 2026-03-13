/** Design system Athena — minimaliste haute précision
 *  Palette: fond slate-50, texte slate-900, accents emerald-600
 *  Titres: Sans-Serif (Inter), gras, italique, tracking élevé
 *  Corps: Serif (Source Serif 4), text-slate-600, leading-relaxed
 *  Ce fichier sert de référence ; le layout utilise la config CDN (script dans main.php).
 */
module.exports = {
  content: ['views/**/*.php', 'public/**/*.js'],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
        serif: ['"Source Serif 4"', 'Georgia', 'serif'],
      },
      letterSpacing: {
        architect: '0.3em',
        blueprint: '0.5em',
      },
    },
  },
  plugins: [],
};
