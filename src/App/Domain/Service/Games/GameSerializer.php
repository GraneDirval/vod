<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 07.02.19
 * Time: 12:56
 */

namespace App\Domain\Service\Games;


use App\Domain\Entity\Game;

class GameSerializer
{
    /**
     * @var ImagePathProvider
     */
    private $provider;

    /**
     * GameSerializer constructor.
     *
     * @param ImagePathProvider $provider
     */
    public function __construct(ImagePathProvider $provider)
    {
        $this->provider = $provider;
    }

    public function serialize(Game $game): array
    {
        return [
            'uuid' => $game->getUuid(),
            'title' => $game->getTitle(),
            'description' => $game->getDescription(),
            'iconPath' => $this->provider->getGamePosterPath($game->getIcon())
        ];
    }
}