<?php
declare(strict_types=1);

namespace Belvg\Sqs\Model\Config\Source;

class ConfigToUse implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'deployment', 'label' => __('No')], 
            ['value' => 'system', 'label' => __('Yes')]
        ];
    }
}