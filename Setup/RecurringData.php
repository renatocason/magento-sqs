<?php
declare(strict_types=1);

namespace Belvg\Sqs\Setup;

use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Communication\ConfigInterface as CommunicationConfig;
use Magento\Framework\MessageQueue\ConfigInterface as QueueConfig;
use Belvg\Sqs\Model\Config;


/**
 * RecurringData class to search for queues names and fill/update a system config serialized array
 */
class RecurringData implements InstallDataInterface
{
    /**
     * @var ConfigInterface
     */
    private $resourceConfig;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;
    
    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var CommunicationConfig
     */
    private $communicationConfig;

    /**
     * @var QueueConfig
     */
    private $queueConfig;

    /**
     * Constructor
     *
     * @param ConfigInterface $resourceConfig
     * @param ScopeConfigInterface $scopeConfig
     * @param SerializerInterface $serializer
     * @param CommunicationConfig $communicationConfig
     * @param QueueConfig $queueConfig
     */
    public function __construct(
        ConfigInterface $resourceConfig,
        ScopeConfigInterface $scopeConfig,
        SerializerInterface $serializer,
        CommunicationConfig $communicationConfig,
        QueueConfig $queueConfig
    ){
        $this->resourceConfig = $resourceConfig;
        $this->scopeConfig = $scopeConfig;
        $this->serializer = $serializer;
        $this->communicationConfig = $communicationConfig;
        $this->queueConfig = $queueConfig;
    }

    /**
     * Save config value with default scope
     * 
     * @param string $path
     * @param string $value
     */
    private function saveConfig($path, $value):void
    {
        $this->resourceConfig->saveConfig($path, $value, ScopeConfigInterface::SCOPE_TYPE_DEFAULT, \Magento\Store\Model\Store::DEFAULT_STORE_ID);
    }

    /**
     * Return list of queue names, that are available for connection
     *
     * @param string $connection
     * @return array List of queue names
     */
    private function getQueuesList($connection)
    {
        $queues = [];
        foreach ($this->queueConfig->getConsumers() as $consumer) {
            if ($consumer[QueueConfig::CONSUMER_CONNECTION] === $connection) {
                $queues[] = $consumer[QueueConfig::CONSUMER_QUEUE];
            }
        }
        foreach (array_keys($this->communicationConfig->getTopics()) as $topicName) {
            if ($this->queueConfig->getConnectionByTopic($topicName) === $connection) {
                $queues = array_merge($queues, $this->queueConfig->getQueuesByTopic($topicName));
            }
        }
        $queues = array_unique($queues);
        return $queues;
    }

    /**
     * {@inheritdoc}
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        // Get XML actually declared queues names
        $queuesList = getQueuesList(Config::SQS_CONFIG);

        // Get the system config queues names serialized array
        $sysConfQueuesNames = $this->serializer->unserialize($this->scopeConfig->getValue(Config::XML_PATH_SQS_QUEUE_NAME, \Magento\Store\Model\ScopeInterface::SCOPE_STORE));

        foreach ($queuesList as $queueName) {
            if (!isset($sysConfQueuesNames[$queueName])) {
                $sysConfQueuesNames[$queueName] = '';
            }
        }

        $newSysConfQueuesNames = $this->serializer->serialize($sysConfQueuesNames);

        $this->saveConfig(Config::XML_PATH_SQS_QUEUE_NAME,$newSysConfQueuesNames);

    }
}