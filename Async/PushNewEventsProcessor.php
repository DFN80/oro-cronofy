<?php
namespace Dfn\Bundle\OroCronofyBundle\Async;

use Oro\Component\MessageQueue\Transport\MessageInterface;
use Oro\Component\MessageQueue\Transport\SessionInterface;

/**
 * Class PushNewEventsProcessor
 * @package Dfn\Bundle\OroCronofyBundle\Async
 */
class PushNewEventsProcessor extends MultipleBaseProcessor
{
    /**
     * {@inheritdoc}
     */
    public function process(MessageInterface $message, SessionInterface $session)
    {
        $this->setSingleTopic(Topics::PUSH_NEW_EVENT);
        return parent::process($message, $session);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedTopics()
    {
        return [Topics::PUSH_NEW_EVENTS];
    }
}
