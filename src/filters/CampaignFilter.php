<?php
namespace Gladeye\CampaignManager\filters;

use craft\elements\Entry;
use craft\webhooks\filters\FilterInterface;
use Gladeye\CampaignManager\elements\Campaign;
use yii\base\Event;

class CampaignFilter implements FilterInterface
{
    public static function displayName(): string
    {
        return 'Entry was published as part of a campaign';
    }

    public static function show(string $class, string $event): bool
    {
        // Only show this filter if the Sender Class is set to 'craft\elements\Entry'
        return $class === Entry::class;
    }

    public static function check(Event $event, bool $value): bool
    {
        // Filter basen on whether the entry's type is 'article':
        /** @var Entry $entry */
        $entry = $event->sender;

        return (array_search($entry->getSourceId(), Campaign::$entriesPublishedThisSession) !== false) === $value;
    }

    public static function isSelectable() : bool {
        return true;
    }
}




