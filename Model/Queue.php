<?php
/**
 *  @package BelVG AWS Sqs.
 *  @copyright 2018
 *
 */

namespace Belvg\Sqs\Model;

use Belvg\Sqs\Helper\Data;
use Enqueue\Sqs\SqsMessage;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\MessageQueue\EnvelopeFactory;
use Magento\Framework\MessageQueue\EnvelopeInterface;
use Magento\Framework\MessageQueue\QueueInterface;
use Magento\Framework\Serialize\SerializerInterface;
use PhpAmqpLib\Wire\AMQPTable;
use Psr\Log\LoggerInterface;

/**
 * Class Queue
 */
class Queue implements QueueInterface
{
    const TIMEOUT_PROCESS = 20000;

    /**
     * @var Config
     */
    private $sqsConfig;

    /**
     * @var string
     */
    private $queueName;

    /**
     * @var EnvelopeFactory
     */
    private $envelopeFactory;

    /**
     * @var LoggerInterface $logger
     */
    private $logger;

    /**
     * @var \Enqueue\Sqs\SqsDestination
     */
    private $queue;

    /**
     * @var \Enqueue\Sqs\SqsConsumer
     */
    private $consumer;

    /**
     * @var Data
     */
    private $helper;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * Initialize dependencies.
     *
     * @param Config $sqsConfig
     * @param EnvelopeFactory $envelopeFactory
     * @param string $queueName
     * @param LoggerInterface $logger
     * @param Data $helper
     * @param SerializerInterface $serializer
     */
    public function __construct(
        Config $sqsConfig,
        EnvelopeFactory $envelopeFactory,
        $queueName,
        LoggerInterface $logger,
        Data $helper = null,
        SerializerInterface $serializer
    )
    {
        $this->sqsConfig = $sqsConfig;
        $this->queueName = $this->getRemappedQueueName($queueName);
        $this->envelopeFactory = $envelopeFactory;
        $this->logger = $logger;
        $this->helper = $helper ?: ObjectManager::getInstance()->get(Data::class);
        $this->serializer = $serializer;
    }

    /**
     * Get the remapped queue name
     *
     * @param string $queueName
     * @return string
     */
    public function getRemappedQueueName(string $queueName){

        $sysConfQueuesNames = $this->sqsConfig->getNamesMapping();

        foreach ($sysConfQueuesNames as $sysConfQueueName => $sysConfQueueNameData) {
            if ($sysConfQueueName == $queueName){
                return $sysConfQueueNameData[Config::NAMES_MAPPING_SQS_NAME_KEY];
            }
        }

        return $queueName;
    }

    /**
     * {@inheritdoc}
     */
    public function dequeue()
    {
        /**
         * @var \Enqueue\Sqs\SqsMessage $message
         */
        $message = $this->createConsumer()->receive(self::TIMEOUT_PROCESS);
        if (null !== $message) {
            $envelope = $this->createEnvelop($message);
            return $envelope;
        }
        return null;

    }

    /**
     * @return \Enqueue\Sqs\SqsConsumer
     */
    public function createConsumer()
    {
        if (!$this->consumer) {
            $this->consumer = $this->sqsConfig->getConnection()->createConsumer($this->getQueue());
        }

        return $this->consumer;
    }

    /**
     * @return \Enqueue\Sqs\SqsDestination
     */
    public function getQueue()
    {
        return $this->sqsConfig->getConnection()->createQueue($this->getQueueName());
    }

    /**
     * @return string
     */
    public function getQueueName()
    {
        return $this->helper->prepareQueueName($this->queueName, true);
    }

    /**
     * @param SqsMessage $message
     * @return \Magento\Framework\MessageQueue\Envelope
     */
    protected function createEnvelop(SqsMessage $message)
    {
        $messageBody = $this->serializer->unserialize($message->getBody());

        if (isset($messageBody['topic_name'])){
            $topicName = $messageBody['topic_name'];
        } else {
            $topicName = $this->queueName;
        }

        $properties = is_array($message->getProperties()) ? $message->getProperties() : [];
        $properties['topic_name'] = $topicName;
        $properties['receiptHandle'] = $message->getReceiptHandle();
        $properties['message_id'] = $message->getReceiptHandle();

        return $this->envelopeFactory->create([
            'body' => $message->getBody(),
            'properties' => $properties
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function acknowledge(EnvelopeInterface $envelope)
    {
        $message = $this->createMessage($envelope);
        $this->createConsumer()->acknowledge($message);
        // @codingStandardsIgnoreEnd
    }

    /**
     * It's possible to use after* plugin to set `message_group_id` if your queue type is FIFO:
     * $message->setMessageGroupId($groupId)
     *
     * @param EnvelopeInterface $envelopereceiptHandle
     * @return \Enqueue\Sqs\SqsMessage
     */
    public function createMessage(EnvelopeInterface $envelope)
    {
        $properties = $envelope->getProperties();
        $receiptHandler = array_key_exists('receiptHandle', $properties) ? $properties['receiptHandle'] : null;
        $message = $this->sqsConfig->getConnection()->createMessage($envelope->getBody(), $properties);
        if ($receiptHandler) {
            $message->setReceiptHandle($receiptHandler);
        }
        if (substr($this->getQueueName(), -5) == '.fifo') {
            $message->setMessageGroupId($this->sqsConfig->getValue(Config::MESSAGE_GROUP_ID));
        }
        return $message;
    }

    /**
     * {@inheritdoc}
     */
    public function subscribe($callback, int $qtyOfMessages = null)
    {
        $index = 0;
        while (true) {
            /**
             * @var \Enqueue\Sqs\SqsMessage $message
             */
            if ($message = $this->createConsumer()->receive(self::TIMEOUT_PROCESS)) {
                $index++;
                $envelope = $this->createEnvelop($message);

                if ($callback instanceof \Closure) {
                    $callback($envelope);
                } else {
                    call_user_func($callback, $envelope);
                }
                //$this->createConsumer()->acknowledge($message);
                if (null !== $qtyOfMessages && $index >= $qtyOfMessages) {
                    break;
                }
            }
        }
    }

    /**
     * (@inheritdoc)
     */
    public function reject(EnvelopeInterface $envelope, $requeue = true, $rejectionMessage = null)
    {
        $message = $this->createMessage($envelope);
        $consumer = $this->createConsumer();
        $consumer->reject($message, $requeue);
    }

    /**
     * (@inheritdoc)
     */
    public function push(EnvelopeInterface $envelope)
    {
        $message = $this->createMessage($envelope);
        $this->sqsConfig->getConnection()->createProducer()->send($this->getQueue(), $message);
    }

    /**
     * @return \Enqueue\Sqs\SqsContext
     */
    public function getConnection()
    {
        return $this->sqsConfig->getConnection();
    }

}