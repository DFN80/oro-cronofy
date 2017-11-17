<?php
namespace Dfn\Bundle\OroCronofyBundle\Async;

use Psr\Log\LoggerInterface;

use Oro\Component\MessageQueue\Client\MessageProducerInterface;
use Oro\Component\MessageQueue\Client\TopicSubscriberInterface;
use Oro\Component\MessageQueue\Consumption\MessageProcessorInterface;
use Oro\Component\MessageQueue\Transport\MessageInterface;
use Oro\Component\MessageQueue\Transport\SessionInterface;
use Oro\Component\MessageQueue\Util\JSON;

/**
 * Class MultipleBaseProcessor
 * @package Dfn\Bundle\OroCronofyBundle\Async
 */
class MultipleBaseProcessor implements MessageProcessorInterface, TopicSubscriberInterface
{
    /** @var LoggerInterface */
    private $logger;

    /** @var MessageProducerInterface  */
    private $messageProducer;

    /** @var string */
    private $singleTopic;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(
        LoggerInterface $logger,
        MessageProducerInterface $messageProducer
    ) {
        $this->logger = $logger;
        $this->messageProducer = $messageProducer;
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

        //Create a push new event message foreach event in array. This way if one fails it can be individually re-queued
        foreach ($data as $event) {
            $this->messageProducer->send($this->singleTopic, json_encode($event));
        }

        //Acknowledge message
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
     * Set the topic to send individual messages to
     *
     * @param $singleTopic
     */
    protected function setSingleTopic($singleTopic)
    {
        $this->singleTopic = $singleTopic;
    }
}
