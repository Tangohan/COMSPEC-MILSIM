<?php

declare(strict_types=1);

namespace App\Services\Moderation;

/**
 * Enchaîne ClamAV + heuristiques texte / nom de fichier et applique les seuils.
 */
final class ContentModerationOrchestrator
{
    public function __construct(
        private ContentModerationConfig $config,
        private ClamAvScanner $clamAv,
        private HeuristicTextModerator $heuristicText
    ) {
    }

    /**
     * Analyse fichier sur disque (binaire + métadonnées nom).
     */
    public function scanBinaryFile(string $absolutePath, string $mime, string $originalName): ModerationScanResult
    {
        if (!$this->config->enabled) {
            return new ModerationScanResult(
                ModerationArtifactState::CLEAN,
                0,
                [],
                ['disabled' => true]
            );
        }

        $scanLog = [];
        $reasonCodes = [];
        $score = 0;

        $clam = $this->clamAv->scanFile($absolutePath);
        $scanLog['clamav'] = $clam;
        if ($clam['infected']) {
            return new ModerationScanResult(
                ModerationArtifactState::REJECTED,
                100,
                ['malware'],
                $scanLog
            );
        }

        $meta = $this->heuristicText->scoreFilename($originalName);
        $scanLog['meta'] = $meta;
        $score = max($score, $meta['score']);
        $reasonCodes = array_merge($reasonCodes, $meta['codes']);

        return $this->finalizeScore($score, array_values(array_unique($reasonCodes)), $scanLog);
    }

    /**
     * Texte uniquement (courrier, champs métadonnées concaténés).
     *
     * @param string[] $extraPlainSegments segments additionnels (titres, etc.)
     */
    public function scanTextContent(string $htmlOrText, array $extraPlainSegments = []): ModerationScanResult
    {
        if (!$this->config->enabled) {
            return new ModerationScanResult(ModerationArtifactState::CLEAN, 0, [], ['disabled' => true]);
        }

        $plain = trim($htmlOrText);
        if (str_contains($plain, '<')) {
            $t = $this->heuristicText->scoreHtml($plain);
        } else {
            $t = $this->heuristicText->scorePlainText($plain);
        }
        $score = $t['score'];
        $codes = $t['codes'];
        foreach ($extraPlainSegments as $seg) {
            $seg = trim($seg);
            if ($seg === '') {
                continue;
            }
            $u = $this->heuristicText->scorePlainText($seg);
            $score = max($score, $u['score']);
            $codes = array_merge($codes, $u['codes']);
        }

        return $this->finalizeScore($score, array_values(array_unique($codes)), ['text' => $t]);
    }

    /**
     * @param string[] $reasonCodes
     * @param array<string, mixed> $scanLog
     */
    private function finalizeScore(int $score, array $reasonCodes, array $scanLog): ModerationScanResult
    {
        $score = max(0, min(100, $score));
        if ($score >= $this->config->thresholdHigh) {
            return new ModerationScanResult(ModerationArtifactState::REJECTED, $score, $reasonCodes, $scanLog);
        }
        if ($score >= $this->config->thresholdLow) {
            return new ModerationScanResult(ModerationArtifactState::QUARANTINED, $score, $reasonCodes, $scanLog);
        }

        return new ModerationScanResult(ModerationArtifactState::CLEAN, $score, $reasonCodes, $scanLog);
    }
}
