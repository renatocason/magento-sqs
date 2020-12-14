<?php
/**
 *  @package BelVG AWS Sqs.
 *  @copyright 2018
 *
 */

namespace Belvg\Sqs\Model;

use Enqueue\Sqs\SqsConnectionFactory;
use Enqueue\Sqs\SqsContext;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Encryption\EncryptorInterface;

/**
 * Reads the SQS config in the system configuration or, alternatively, in the deployed environment configuration
 */
class Config
{
    /**
     * Queue config key
     */
    const QUEUE_CONFIG = 'queue';

    /**
     * Sqs config key
     */
    const SQS_CONFIG = 'sqs';

    const REGION = 'region';
    const VERSION = 'version';
    const ACCESS_KEY = 'access_key';
    const SECRET_KEY = 'secret_key';
    const NAMES_MAPPING = 'names_mapping';
    const PREFIX = 'prefix';
    const ENDPOINT = 'endpoint';
    const MESSAGE_GROUP_ID = 'message_group_id';

    const XML_PATH_SQS_QUEUE_CONFIG_TO_USE = 'system/sqs/config_to_use';
    const XML_PATH_SQS_QUEUE_REGION = 'system/sqs/region';
    const XML_PATH_SQS_QUEUE_VERSION = 'system/sqs/version';
    const XML_PATH_SQS_QUEUE_ACCESS_KEY = 'system/sqs/access_key';
    const XML_PATH_SQS_QUEUE_SECRET_KEY = 'system/sqs/secret_key';
    const XML_PATH_SQS_QUEUE_NAMES_MAPPING = 'system/sqs/names_mapping';
    const XML_PATH_SQS_QUEUE_MESSAGE_GROUP_ID = 'system/sqs/message_group_id';

    const NAMES_MAPPING_XML_NAME_KEY = 'xml_name';
    const NAMES_MAPPING_XML_NAME_LABEL = 'XML name';
    const NAMES_MAPPING_SQS_NAME_KEY = 'sqs_name';
    const NAMES_MAPPING_SQS_NAME_LABEL = 'SQS name';

    /**
     * Deployment configuration
     *
     * @var DeploymentConfig
     */
    private $deploymentConfig;

    /**
     * @var SqsClient
     */
    private $connection;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @var array
     */
    private $channels = [];

    /**
     * Associative array of SQS configuration
     *
     * @var array
     */
    private $data;

    /**
     * Constructor
     *
     * Example environment config:
     * <code>
     * 'queue' =>
     *     [
     *         'sqs' => [
     *             'region' => 'region',
     *             'version' => 'latest',
     *             'access_key' => '123456',
     *             'secret_key' => '123456',
     *             'names_mapping' => [
     *                  'xml-esample.fifo' => [
     *                      'xml_name' => 'xml-example.fifo',
     *                      'sqs_name' => 'sqs-example.fifo'
     *                  ]
     *              ],
     *             'prefix' => 'magento',
     *             'endpoint' => 'http://localhost:4575'
     *         ],
     *     ],
     * </code>
     *
     * @param DeploymentConfig $config
     */
    public function __construct(
        DeploymentConfig $config,
        ScopeConfigInterface $scopeConfig,
        SerializerInterface $serializer,
        EncryptorInterface $encryptor
    ){
        $this->deploymentConfig = $config;
        $this->scopeConfig = $scopeConfig;
        $this->serializer = $serializer;
        $this->encryptor = $encryptor;
    }

    /**
     * Return SQS client
     * @return SqsContext
     */
    public function getConnection()
    {
        if (!isset($this->connection)) {
            $this->connection = (new SqsConnectionFactory(
                [
                    'region' => $this->getValue(Config::REGION),
                    'key' => $this->getValue(Config::ACCESS_KEY),
                    'secret' => $this->getValue(Config::SECRET_KEY),
                    'endpoint' => $this->getValue(Config::ENDPOINT)
                ]
            ))->createContext();
        }

        return $this->connection;
    }

    /**
     * Returns the configuration set for the key.
     *
     * @param string $key
     * @return string
     */
    public function getValue($key)
    {
        // Load the configuration from system configs
        $this->loadSystemConfigs();

        // If no data in system configs, load deployment configs
        $this->load();

        return isset($this->data[$key]) ? $this->data[$key] : null;
    }

    /**
     * Load the configuration for SQS from deployment configs
     *
     * @return void
     */
    private function load()
    {
        if (null === $this->data) {
            $queueConfig = $this->deploymentConfig->getConfigData(self::QUEUE_CONFIG);
            $this->data = isset($queueConfig[self::SQS_CONFIG]) ? $queueConfig[self::SQS_CONFIG] : [];
        }
    }

    /**
     * Load the configuration for SQS from system configs
     *
     * @return void
     */
    private function loadSystemConfigs()
    {
        if ((null === $this->data) && ($this->getSysConfig(self::XML_PATH_SQS_QUEUE_CONFIG_TO_USE) == 'system')) {
            if (!empty($this->getSysConfig(self::XML_PATH_SQS_QUEUE_REGION)))
                $this->data[self::REGION] = $this->getSysConfig(self::XML_PATH_SQS_QUEUE_REGION);

            if (!empty($this->getSysConfig(self::XML_PATH_SQS_QUEUE_VERSION))) 
                $this->data[self::VERSION] = $this->getSysConfig(self::XML_PATH_SQS_QUEUE_VERSION);

            if (!empty($this->getSysConfig(self::XML_PATH_SQS_QUEUE_ACCESS_KEY)))
                $this->data[self::ACCESS_KEY] = $this->getSysConfig(self::XML_PATH_SQS_QUEUE_ACCESS_KEY);

            if (!empty($this->getSysConfig(self::XML_PATH_SQS_QUEUE_SECRET_KEY)))
                $this->data[self::SECRET_KEY] = $this->encryptor->decrypt($this->getSysConfig(self::XML_PATH_SQS_QUEUE_SECRET_KEY));

            if (!empty($this->getSysConfig(self::XML_PATH_SQS_QUEUE_MESSAGE_GROUP_ID)))
                $this->data[self::MESSAGE_GROUP_ID] = $this->getSysConfig(self::XML_PATH_SQS_QUEUE_MESSAGE_GROUP_ID);

            if (!empty($this->getSysConfig(self::XML_PATH_SQS_QUEUE_NAMES_MAPPING)))
                $this->data[self::NAMES_MAPPING] = $this->serializer->unserialize($this->getSysConfig(self::XML_PATH_SQS_QUEUE_NAMES_MAPPING));
        }
    }

    /**
     * Get the system config value for the XML path
     *
     * @param string $path
     * @return string
     */
    private function getSysConfig($path)
    {
        return $this->scopeConfig->getValue($path,ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get queues names mapping
     *
     * @return array
     */
    public function getNamesMapping(){
        return empty($this->getValue(self::NAMES_MAPPING)) ? [] : $this->getValue(self::NAMES_MAPPING);
    }
}
