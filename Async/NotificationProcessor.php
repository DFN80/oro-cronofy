<?php
namespace Dfn\Bundle\OroCronofyBundle\Async;

use Oro\Component\MessageQueue\Transport\MessageInterface;
use Oro\Component\MessageQueue\Transport\SessionInterface;

/**
 * Class NotificationProcessor
 * @package Dfn\Bundle\OroCronofyBundle\Async
 */
class NotificationProcessor extends SingleBaseProcessor
{
    /**
     * {@inheritdoc}
     */
    public function process(MessageInterface $message, SessionInterface $session)
    {
        $this->setMethod('processNotification');
        return parent::process($message, $session);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedTopics()
    {
        return [Topics::NOTIFICATION];
    }
}