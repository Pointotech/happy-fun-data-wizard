<?php

namespace Pointotech\Words;

class PluralWords
{
    static function find(string $pluralWord): ?string
    {
        if (array_key_exists($pluralWord, self::ALL)) {
            return $pluralWord;
        } else {
            return null;
        }
    }

    static function getSingular(string $pluralWord): string
    {
        if (array_key_exists($pluralWord, self::ALL)) {
            return self::ALL[$pluralWord]['singular'];
        } else {
            return $pluralWord;
        }
    }

    private const ALL = [
        'actions' => [
            'singular' => 'action',
        ],
        'agents' => [
            'singular' => 'agent',
        ],
        'authorizations' => [
            'singular' => 'authorization',
        ],
        'autofills' => [
            'singular' => 'autofill',
        ],
        'brands' => [
            'singular' => 'brand',
        ],
        'brokerages' => [
            'singular' => 'brokerage',
        ],
        'categories' => [
            'singular' => 'category',
        ],
        'chapters' => [
            'singular' => 'chapter',
        ],
        'clicks' => [
            'singular' => 'click',
        ],
        'clients' => [
            'singular' => 'client',
        ],
        'comments' => [
            'singular' => 'comment',
        ],
        'countries' => [
            'singular' => 'country',
        ],
        'details' => [
            'singular' => 'detail',
        ],
        'distributions' => [
            'singular' => 'distribution',
        ],
        'encoders' => [
            'singular' => 'encoder',
        ],
        'engagements' => [
            'singular' => 'engagement',
        ],
        'emails' => [
            'singular' => 'email',
        ],
        'groups' => [
            'singular' => 'group',
        ],
        'histories' => [
            'singular' => 'history',
        ],
        'images' => [
            'singular' => 'image',
        ],
        'interactions' => [
            'singular' => 'interaction',
        ],
        'invites' => [
            'singular' => 'invite',
        ],
        'jobs' => [
            'singular' => 'job',
        ],
        'keys' => [
            'singular' => 'key',
        ],
        'loads' => [
            'singular' => 'load',
        ],
        'logs' => [
            'singular' => 'log',
        ],
        'marketplaces' => [
            'singular' => 'marketplace',
        ],
        'medias' => [
            'singular' => 'media',
        ],
        'metrics' => [
            'singular' => 'metric',
        ],
        'offices' => [
            'singular' => 'office',
        ],
        'partners' => [
            'singular' => 'partner',
        ],
        'photos' => [
            'singular' => 'photo',
        ],
        'plans' => [
            'singular' => 'plan',
        ],
        'playlists' => [
            'singular' => 'playlist',
        ],
        'podcasts' => [
            'singular' => 'podcast',
        ],
        'portions' => [
            'singular' => 'portion',
        ],
        'posts' => [
            'singular' => 'post',
        ],
        'previews' => [
            'singular' => 'preview',
        ],
        'projects' => [
            'singular' => 'project',
        ],
        'prospects' => [
            'singular' => 'prospect',
        ],
        'questions' => [
            'singular' => 'question',
        ],
        'recipients' => [
            'singular' => 'recipient',
        ],
        'replies' => [
            'singular' => 'reply',
        ],
        'responses' => [
            'singular' => 'response',
        ],
        'roles' => [
            'singular' => 'role',
        ],
        'settings' => [
            'singular' => 'setting',
        ],
        'shares' => [
            'singular' => 'share',
        ],
        'statuses' => [
            'singular' => 'status',
        ],
        'subscriptions' => [
            'singular' => 'subscription',
        ],
        'tags' => [
            'singular' => 'tag',
        ],
        'themes' => [
            'singular' => 'theme',
        ],
        'tokens' => [
            'singular' => 'token',
        ],
        'topics' => [
            'singular' => 'topic',
        ],
        'transactions' => [
            'singular' => 'transaction',
        ],
        'types' => [
            'singular' => 'type',
        ],
        'usages' => [
            'singular' => 'usage',
        ],
        'users' => [
            'singular' => 'user',
        ],
        'videos' => [
            'singular' => 'video',
        ],
        'views' => [
            'singular' => 'view',
        ],
    ];
}
