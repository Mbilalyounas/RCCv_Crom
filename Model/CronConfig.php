<?php

namespace RCCsv\Cron\Model;

class CronConfig extends \Magento\Framework\App\Config\Value
{
    const CRON_STRING_PATH = 'crontab/default/jobs/rccsv_cron_cron_job/schedule/cron_expr';

    const CRON_MODEL_PATH = 'crontab/default/jobs/rccsv_cron_cron_job/run/model';

    protected $_configValueFactory;

    protected $_runModelPath = '';

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Framework\App\Config\ValueFactory $configValueFactory,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        $runModelPath = '',
        array $data = []
    )
    {
        $this->_runModelPath = $runModelPath;
        $this->_configValueFactory = $configValueFactory;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }


    public function afterSave()
    {
       
        $frequency = $this->getData('groups/RCCsv/fields/frequency/value');

        try {
            $this->_configValueFactory->create()->load(
                self::CRON_STRING_PATH,
                'path'
            )->setValue(
                 $frequency
            )->setPath(
                self::CRON_STRING_PATH
            )->save();
            $this->_configValueFactory->create()->load(
                self::CRON_MODEL_PATH,
                'path'
            )->setValue(
                $this->_runModelPath
            )->setPath(
                self::CRON_MODEL_PATH
            )->save();
        } catch (\Exception $e) {
            throw new \Exception(__('We can\'t save the cron expression.'));
        }

        return parent::afterSave();
    }
}