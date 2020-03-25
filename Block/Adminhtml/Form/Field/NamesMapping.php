<?php
declare(strict_types=1);

namespace Belvg\Sqs\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;

use Belvg\Sqs\Model\Config;

/**
 * Class NamesMapping
 */
class NamesMapping extends AbstractFieldArray
{
    /**
     * Prepare rendering the new field by adding all the needed columns
     */
    protected function _prepareToRender()
    {
        $this->addColumn(Config::NAMES_MAPPING_XML_NAME_KEY, ['label' => __(Config::NAMES_MAPPING_XML_NAME_LABEL), 'class' => 'xml-name required-entry']);
        $this->addColumn(Config::NAMES_MAPPING_SQS_NAME_KEY, ['label' => __(Config::NAMES_MAPPING_SQS_NAME_LABEL)]);
        $this->_addAfter = false;
    }
}