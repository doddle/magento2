<?php
namespace Doddle\Returns\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;

class TestApiButton extends Field
{
    /**
     * @return $this|Field
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        if (!$this->getTemplate()) {
            $this->setTemplate('Doddle_Returns::system/config/test_api_button.phtml');
        }
        return $this;
    }

    /**
     * Unset some non-related element parameters
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Get the button and scripts contents
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $this->addData(
            [
                'button_label' => __($element->getOriginalData('button_label')),
                'html_id' => $element->getHtmlId()
            ]
        );

        return $this->_toHtml();
    }

    /**
     * @return \Magento\Framework\Phrase
     */
    public function getSuccessMessage()
    {
        return __('API credentials successfully authenticated');
    }

    /**
     * @return \Magento\Framework\Phrase
     */
    public function getFailMessage()
    {
        return __('Invalid API credentials');
    }
}
