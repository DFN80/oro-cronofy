<?php
namespace Dfn\Bundle\OroCronofyBundle\Async;

use Oro\Component\MessageQueue\Transport\MessageInterface;
use Oro\Component\MessageQueue\Transport\SessionInterface;

/**
 * Class SyncProcessor
 * @package Dfn\Bundle\OroCronofyBundle\Async
 */
class SyncProcessor extends SingleBaseProcessor
{
    /**
     * {@inheritdoc}
     */
    public function process(MessageInterface $message, SessionInterface $session)
    {
        $this->setMethod('processSync');
        return parent::process($message, $session);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedTopics()
    {
        return [Topics::SYNC];
    }
}
