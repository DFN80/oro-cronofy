<?php
namespace Dfn\Bundle\OroCronofyBundle\Async;

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

    /** @var object  */
    private $handler;

    /** @var string */
    private $method;

    /**
     * @param LoggerInterface $logger
     * @param object $handler
     */
    public function __construct(
        LoggerInterface $logger,
        $handler
    ) {
        $this->logger = $logger;
        $this->handler = $handler;
    }

    /**
     * {@inheritdoc}
     */
    public function process(MessageInterface $message, SessionInterface $session)
    {
        $data = JSON::decode($message->getBody());

        if (!count($data)) {
            $this->logger->critical(
                sprintf('Got invalid message. "%s"', $message->getBody()),
                ['message' => $message]
            );

            return self::REJECT;
        }

        //Process message via the injected handler and set method
        try {
            $this->handler->{$this->method}($data);
        } catch (\Exception $e) {
            var_dump('error');
            $this->logger->critical('Failed processing Cronofy message. ' . $e->getMessage());
            $this->logger->critical($e);
            //TODO Check # of attempts, if greater the X log full message and don't requeue
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

    /**
     * @param string $method
     */
    protected function setMethod($method)
    {
        $this->method = $method;
    }
}
