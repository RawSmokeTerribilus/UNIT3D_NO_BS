<?php

declare(strict_types=1);

/**
 * NOBS — Nuclear Order Bit Syndicate
 *
 * Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>
 *
 * Obra original de NOBS, parte de un derivado de UNIT3D Community Edition
 * (HDInnovations) del que hereda la licencia.
 *
 * @project    NOBS — https://nobs.rawsmoke.net
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html  GNU AGPL v3.0
 */

namespace App\Services;

use App\Enums\UserGroup;
use App\Jobs\SendDeleteUserMail;
use App\Models\Comment;
use App\Models\FailedLoginAttempt;
use App\Models\FreeleechToken;
use App\Models\History;
use App\Models\Like;
use App\Models\Message;
use App\Models\Participant;
use App\Models\Peer;
use App\Models\Post;
use App\Models\PrivateMessage;
use App\Models\Scopes\ApprovedScope;
use App\Models\Thank;
use App\Models\Topic;
use App\Models\Torrent;
use App\Models\User;

/**
 * Aniquilar (prunear) una cuenta con el ritual nativo: el cuerpo de
 * AutoSoftDeleteDisabledUsers, sacado aqui para que lo use tambien la poda de
 * baneados por H&R sin copiarlo (el 22 y el 25-09 se replico a mano por
 * consola). Sus subidas, comentarios y mensajes pasan a System.
 */
final class UserPrune
{
    public static function prune(User $user, int $deletedBy = User::SYSTEM_USER_ID): void
    {
        $user->update([
            'can_download' => false,
            'group_id'     => UserGroup::PRUNED->value,
            'deleted_by'   => $deletedBy,
        ]);

        Torrent::withoutGlobalScope(ApprovedScope::class)->where('user_id', '=', $user->id)->update([
            'user_id' => User::SYSTEM_USER_ID,
        ]);

        Comment::where('user_id', '=', $user->id)->update([
            'user_id' => User::SYSTEM_USER_ID,
        ]);

        Post::where('user_id', '=', $user->id)->update([
            'user_id' => User::SYSTEM_USER_ID,
        ]);

        Topic::where('first_post_user_id', '=', $user->id)->update([
            'first_post_user_id' => User::SYSTEM_USER_ID,
        ]);

        Topic::where('last_post_user_id', '=', $user->id)->update([
            'last_post_user_id' => User::SYSTEM_USER_ID,
        ]);

        PrivateMessage::where('sender_id', '=', $user->id)->update([
            'sender_id' => User::SYSTEM_USER_ID,
        ]);

        Participant::where('user_id', '=', $user->id)->delete();
        Message::where('user_id', '=', $user->id)->delete();
        Like::where('user_id', '=', $user->id)->delete();
        Thank::where('user_id', '=', $user->id)->delete();
        Peer::where('user_id', '=', $user->id)->delete();
        History::where('user_id', '=', $user->id)->delete();
        FailedLoginAttempt::where('user_id', '=', $user->id)->delete();

        // Removes all follows for user
        $user->followers()->detach();
        $user->following()->detach();

        // Removes all FL Tokens for user
        foreach (FreeleechToken::where('user_id', '=', $user->id)->get() as $token) {
            $token->delete();
            cache()->forget('freeleech_token:'.$user->id.':'.$token->torrent_id);
        }

        cache()->forget('user:'.$user->passkey);

        Unit3dAnnounce::removeUser($user);

        dispatch(new SendDeleteUserMail($user));

        $user->delete();
    }
}
