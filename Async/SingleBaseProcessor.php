<?php
namespace Dfn\Bundle\OroCronofyBundle\Async;

use Dfn\Bundle\OroCronofyBundle\Manager\CronofyPushHandler;

use Psr\Log\LoggerInterface;

use Oro\Component\MessageQueue\Client\TopicSubscriberInterface;
use Oro\Component\MessageQueue\Consumption\MessageProcessorInterface;
use Oro\Component\MessageQueue\Transport\MessageInterface;
use Oro\Component\MessageQueue\Transport\SessionInterface;
use Oro\Component\MessageQueue\Util\JSON;

/**
 * Class SingleBaseProcessor
 * @package Dfn\Bundle\OroCronofyBundle\Async
 */
class SingleBaseProcessor implements MessageProcessorInterface, TopicSubscriberInterface
{
    /** @var LoggerInterface */
    private $logger;

    /** @var CronofyPushHandler  */
    private $pushHandler;

    /** @var string */
    private $pushMethod;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(
        LoggerInterface $logger,
        CronofyPushHandler $pushHandler
    ) {
        $this->logger = $logger;
        $this->pushHandler = $pushHandler;
    }

    /**
     * {@inheritdoc}
     */
    public function process(MessageInterface $message, SessionInterface $session)
    {
        $data = JSON::decode($message->getBody());

        if (!isset($data['id'])) {
            $this->logger->critical(
                sprintf('Got invalid message. "%s"', $message->getBody()),
                ['message' => $message]
            );

            return self::REJECT;
        }

        //Push message to cronofy
        try {
            $this->pushHandler->{$this->pushMethod}($data);
        } catch (\Exception $e) {
            var_dump('error');
            $this->logger->critical('Sending event to Cronofy failed.');
            //Check # of attempts, if greater the X log full message and don't requeue
            return self::REQUEUE;
        }

        //Acknowledge message if succeeded
        return self::ACK;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedTopics()
    {
        return false;
    }

    protected function setPushMethod($pushMethod)
    {
        $this->pushMethod = $pushMethod;
    }
}
