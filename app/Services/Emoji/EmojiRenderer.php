<?php

declare(strict_types=1);

namespace App\Services\Emoji;

use JoyPixels\Client;
use JoyPixels\Ruleset;

/**
 * JoyPixels renderer.
 *
 * Replaces hdvinnie/laravel-joypixel-emojis, a forty-line wrapper whose only
 * real job was copying config values onto a JoyPixels\Client. It carried a
 * `joypixels/emoji-toolkit ^6` constraint that pinned emoji support to the
 * 2020 ruleset while the bundled artwork had already moved to v11 — every
 * emoji introduced from Emoji 14 onwards was shipped as a PNG that no code
 * path could ever ask for. Talking to the toolkit directly lifts the cap.
 *
 * The client is expensive to build (the ruleset compiles a ~40 KB regexp), so
 * it is bound as a singleton in AppServiceProvider and resolved from the
 * container rather than instantiated per call as the old wrapper was.
 */
final class EmojiRenderer
{
    private readonly Client $client;

    public function __construct()
    {
        $this->client = new Client(new Ruleset());

        $this->client->emojiSize = (string) config('joypixels.emojiSize');
        $this->client->sprites = (bool) config('joypixels.sprites');
        $this->client->spriteSize = (string) config('joypixels.spriteSize');
        $this->client->emojiVersion = (string) config('joypixels.emojiVersion');
        $this->client->ascii = (bool) config('joypixels.ascii');

        // Assign the path *after* construction, never before: Client::__construct()
        // appends "/{emojiVersion}/png/unicode/{emojiSize}/" to whatever imagePathPNG
        // holds, which is how it turns its own default into a jsDelivr CDN URL.
        // Setting our path first would get that suffix glued onto it.
        $path = config('joypixels.imagePathPNG');

        if (\is_string($path) && $path !== '') {
            $this->client->imagePathPNG = rtrim(url($path), '/').'/';
        }
    }

    /**
     * Unicode and shortnames alike into <img> markup.
     */
    public function toImage(string $string): string
    {
        return $this->client->toImage($string);
    }

    /**
     * Unicode into shortnames, e.g. a literal grinning face into :smile:.
     */
    public function toShort(string $string): string
    {
        return $this->client->toShort($string);
    }

    public function shortnameToImage(string $string): string
    {
        return $this->client->shortnameToImage($string);
    }

    public function client(): Client
    {
        return $this->client;
    }
}
