<?php
namespace Dfn\Bundle\OroCronofyBundle\Async;

use Oro\Component\MessageQueue\Transport\MessageInterface;
use Oro\Component\MessageQueue\Transport\SessionInterface;

/**
 * Class CreateEventProcessor
 * @package Dfn\Bundle\OroCronofyBundle\Async
 */
class CreateEventProcessor extends SingleBaseProcessor
{
    /**
     * {@inheritdoc}
     */
    public function process(MessageInterface $message, SessionInterface $session)
    {
        $this->setMethod('createOrUpdateEvent');
        return parent::process($message, $session);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedTopics()
    {
        return [Topics::CREATE_EVENT];
    }
}
