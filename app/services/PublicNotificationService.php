<?php

declare(strict_types=1);

namespace App\services;

use App\models\Media;
use App\models\PublicNotification;

final class PublicNotificationService
{
    private PublicNotification $notifications;
    private Media $media;

    public function __construct(?PublicNotification $notifications = null, ?Media $media = null)
    {
        $this->notifications = $notifications ?? new PublicNotification();
        $this->media = $media ?? new Media();
    }

    /**
     * Broadcast a newly published title or course to the public header inbox.
     */
    public function notifyTitleCreated(array $title): void
    {
        $titleId = (int) ($title['id'] ?? 0);
        $type = strtoupper((string) ($title['type'] ?? 'TITLE'));
        $name = trim((string) ($title['title_name'] ?? 'Novo titulo'));

        $entityType = $type === 'COURSE' ? 'COURSE' : 'TITLE';
        $headline = $type === 'COURSE' ? 'Novo curso no catalogo' : 'Novo titulo no catalogo';
        $message = $name;
        $link = '/browse';

        if ($type === 'COURSE') {
            $link = '/browse#row-course';
        } elseif ($type === 'SERIES') {
            $link = '/browse#row-series';
        } elseif ($type === 'MOVIE') {
            $link = '/browse#row-movie';
        }

        $this->notifications->create([
            'entity_type' => $entityType,
            'entity_id' => $titleId,
            'title_text' => $headline,
            'message_text' => $message,
            'link_url' => $link,
        ]);
    }

    /**
     * Broadcast new seasons, episodes and lessons using the same public stream.
     */
    public function notifyMediaCreated(array $title, array $media): void
    {
        $titleType = strtoupper((string) ($title['type'] ?? 'SERIES'));
        $titleName = trim((string) ($title['title_name'] ?? 'Catalogo'));
        $mediaId = (int) ($media['id'] ?? 0);
        $seasonNumber = max(1, (int) ($media['season_number'] ?? 1));
        $episodeNumber = max(1, (int) ($media['episode_number'] ?? 1));

        if ($titleType === 'COURSE') {
            $this->notifications->create([
                'entity_type' => 'EPISODE',
                'entity_id' => $mediaId,
                'title_text' => 'Nova licao disponivel',
                'message_text' => sprintf('%s - Modulo %d, licao %d', $titleName, $seasonNumber, $episodeNumber),
                'link_url' => '/watch/' . $mediaId,
            ]);

            if ($this->media->countByTitleSeason((int) ($media['title_id'] ?? 0), $seasonNumber) === 1) {
                $this->notifications->create([
                    'entity_type' => 'SEASON',
                    'entity_id' => (int) ($media['title_id'] ?? 0),
                    'title_text' => 'Novo modulo do curso',
                    'message_text' => sprintf('%s - Modulo %d', $titleName, $seasonNumber),
                    'link_url' => '/browse#row-course',
                ]);
            }

            return;
        }

        if ($titleType === 'SERIES') {
            $this->notifications->create([
                'entity_type' => 'EPISODE',
                'entity_id' => $mediaId,
                'title_text' => 'Novo episodio disponivel',
                'message_text' => sprintf('%s - Temporada %d, episodio %d', $titleName, $seasonNumber, $episodeNumber),
                'link_url' => '/watch/' . $mediaId,
            ]);

            if ($this->media->countByTitleSeason((int) ($media['title_id'] ?? 0), $seasonNumber) === 1) {
                $this->notifications->create([
                    'entity_type' => 'SEASON',
                    'entity_id' => (int) ($media['title_id'] ?? 0),
                    'title_text' => 'Nova temporada liberada',
                    'message_text' => sprintf('%s - Temporada %d', $titleName, $seasonNumber),
                    'link_url' => '/browse#row-series',
                ]);
            }

            return;
        }

        $this->notifications->create([
            'entity_type' => 'TITLE',
            'entity_id' => $mediaId,
            'title_text' => 'Novo conteudo disponivel',
            'message_text' => $titleName,
            'link_url' => '/watch/' . $mediaId,
        ]);
    }
}
