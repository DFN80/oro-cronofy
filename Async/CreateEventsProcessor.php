<?php
namespace Dfn\Bundle\OroCronofyBundle\Async;

use Oro\Component\MessageQueue\Transport\MessageInterface;
use Oro\Component\MessageQueue\Transport\SessionInterface;

/**
 * Class CreateEventsProcessor
 * @package Dfn\Bundle\OroCronofyBundle\Async
 */
class CreateEventsProcessor extends MultipleBaseProcessor
{
    /**
     * {@inheritdoc}
     */
    public function process(MessageInterface $message, SessionInterface $session)
    {
        $this->setSingleTopic(Topics::CREATE_EVENT);
        return parent::process($message, $session);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedTopics()
    {
        return [Topics::CREATE_EVENTS];
    }
}
