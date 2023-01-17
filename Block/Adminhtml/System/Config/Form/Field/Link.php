<?php

namespace RCCsv\Cron\Block\Adminhtml\System\Config\Form\Field;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Link extends Field
{
    public function __construct(
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        return 'Product Import Log (<a href ="'.$this->_urlBuilder->getUrl('import_attribute/index/download', ['file' => 'product']).'">View</a> / <a href ="'.$this->_urlBuilder->getUrl('import_attribute/index/delete', ['file' => 'product']).'">Delete</a>)';
    }
}
