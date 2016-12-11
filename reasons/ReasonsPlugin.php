<?php namespace Craft;

/**
 * Reasons by Mats Mikkel Rummelhoff
 *
 * @author      Mats Mikkel Rummelhoff <http://mmikkel.no>
 * @package     Reasons
 * @since       Craft 2.3
 * @copyright   Copyright (c) 2015, Mats Mikkel Rummelhoff
 * @license     http://opensource.org/licenses/mit-license.php MIT License
 * @link        https://github.com/mmikkel/Reasons-Craft
 */

/**
 * Class ReasonsPlugin
 * @package Craft
 */
class ReasonsPlugin extends BasePlugin
{

    protected $_version = '2.0.0';
    protected $_schemaVersion = '1.1';
    protected $_developer = 'Mats Mikkel Rummelhoff';
    protected $_developerUrl = 'http://mmikkel.no';
    protected $_pluginName = 'Reasons';
    protected $_pluginUrl = 'https://github.com/mmikkel/Reasons-Craft';
    protected $_releaseFeedUrl = 'https://raw.githubusercontent.com/mmikkel/Reasons-Craft/master/releases.json';
    protected $_documentationUrl = 'https://github.com/mmikkel/Reasons-Craft/blob/master/README.md';
    protected $_description = 'Adds conditionals to field layouts.';
    protected $_minVersion = '2.5';

    /**
     * @return string
     */
    public function getName()
    {
        return $this->_pluginName;
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return $this->_version;
    }

    /**
     * @return string
     */
    public function getSchemaVersion()
    {
        return $this->_schemaVersion;
    }

    /**
     * @return string
     */
    public function getDeveloper()
    {
        return $this->_developer;
    }

    /**
     * @return string
     */
    public function getDeveloperUrl()
    {
        return $this->_developerUrl;
    }

    /**
     * @return string
     */
    public function getPluginUrl()
    {
        return $this->_pluginUrl;
    }

    /**
     * @return string
     */
    public function getReleaseFeedUrl()
    {
        return $this->_releaseFeedUrl;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->_description;
    }

    /**
     * @return string
     */
    public function getDocumentationUrl()
    {
        return $this->_documentationUrl;
    }

    /**
     * @return string
     */
    public function getCraftRequiredVersion()
    {
        return $this->_minVersion;
    }

    /**
     * @return mixed
     */
    public function isCraftRequiredVersion()
    {
        return version_compare(craft()->getVersion(), $this->getCraftRequiredVersion(), '>=');
    }

    /**
     * @return bool
     */
    public function onBeforeInstall()
    {
        if (!$this->isCraftRequiredVersion()) {
            craft()->userSession->setError(Craft::t('Reasons requires Craft 2.5 or newer, and was not installed.'));
            return false;
        }
    }

    /**
     *
     */
    public function onBeforeUninstall()
    {
        craft()->fileCache->delete($this->getCacheKey());
    }

    /**
     * @return bool
     */
    public function init()
    {

      parent::init();

      if (!craft()->request->isCpRequest() || craft()->isConsole()) {
          return false;
      }

      if (!$this->isCraftRequiredVersion()) {
          craft()->userSession->setError(Craft::t('Reasons requires Craft 2.5 or newer, and has been disabled.'));
          return false;
      }

      //Craft::import('plugins.reasons.helpers.ReasonsHelper');

      craft()->on('fields.saveFieldLayout', array($this, 'onSaveFieldLayout'));

      if (craft()->request->isActionRequest()) {
        $this->actionRequestInit();
      } else if (!craft()->request->isAjaxRequest()) {
        $this->includeResources();
        craft()->templates->includeJs('Craft.ReasonsPlugin.init('.$this->getData().');');
      }

    }

    /*
    *   Protected methods
    *
    */
    /**
     * @return bool
     */
    protected function actionRequestInit()
    {

      if (!craft()->request->isActionRequest()) {
        return false;
      }

      $actionPath = implode('/', craft()->request->getActionSegments());

      switch ($actionPath) {
        case 'fields/saveField':
          craft()->runController('reasons/fields/saveField');
          break;
        case 'elements/getEditorHtml':
          craft()->runController('reasons/getEditorHtml');
          break;
        case 'entries/switchEntryType':
          craft()->templates->includeJs('Craft.ReasonsPlugin.initPrimaryForm();');
          break;

      }

    }

    /**
     *
     */
    protected function includeResources()
    {
        craft()->templates->includeJsResource('reasons/'.$this->getRevvedResource('reasons.js'));
    }

    /**
     * @return string
     */
    protected function getData()
    {
        $doCacheData = !craft()->config->get('devMode');
        $cacheKey = $this->getCacheKey();
        $data = $doCacheData ? craft()->fileCache->get($cacheKey) : null;
        if (!$data) {
            $data = array(
                'version' => $this->getVersion(),
                'debug' => craft()->config->get('devMode'),
                'conditionals' => $this->getConditionals(),
                'toggleFieldTypes' => $this->getToggleFieldTypes(),
                'toggleFields' => $this->getToggleFields(),
                'fieldIds' => $this->getFieldIds(),
            );
            if ($doCacheData) {
                craft()->fileCache->set($this->getCacheKey(), $data, 1800); // Cache for 30 minutes
            }
        }
        return JsonHelper::encode($data);
    }

    /**
     * @return array
     */
    protected function getConditionals()
    {

        $r = array();
        $sources = array();

        // Entry types
        $entryTypeRecords = EntryTypeRecord::model()->findAll();
        if ($entryTypeRecords) {
            foreach ($entryTypeRecords as $entryTypeRecord) {
                $entryType = EntryTypeModel::populateModel($entryTypeRecord);
                $sources['entryType:' . $entryType->id] = $entryType->fieldLayoutId;
                $sources['section:' . $entryType->sectionId] = $entryType->fieldLayoutId;
            }
        }

        // Category groups
        $allCategoryGroups = craft()->categories->getAllGroups();
        foreach ($allCategoryGroups as $categoryGroup) {
            $sources['categoryGroup:' . $categoryGroup->id] = $categoryGroup->fieldLayoutId;
        }

        // Tag groups
        $allTagGroups = craft()->tags->getAllTagGroups();
        foreach ($allTagGroups as $tagGroup) {
            $sources['tagGroup:' . $tagGroup->id] = $tagGroup->fieldLayoutId;
        }

        // Asset sources
        $allAssetSources = craft()->assetSources->getAllSources();
        foreach ($allAssetSources as $assetSource) {
            $sources['assetSource:' . $assetSource->id] = $assetSource->fieldLayoutId;
        }

        // Global sets
        $allGlobalSets = craft()->globals->getAllSets();
        foreach ($allGlobalSets as $globalSet) {
            $sources['globalSet:' . $globalSet->id] = $globalSet->fieldLayoutId;
        }

        // Matrix block types
        $matrixBlockTypeRecords = MatrixBlockTypeRecord::model()->findAll();
        if ($matrixBlockTypeRecords) {
            foreach ($matrixBlockTypeRecords as $matrixBlockTypeRecord) {
                $matrixBlockType = MatrixBlockTypeModel::populateModel($matrixBlockTypeRecord);
                $sources['matrixBlockType:' . $matrixBlockType->id] = $matrixBlockType->fieldLayoutId;
            }
        }

        // Users
        $usersFieldLayout = craft()->fields->getLayoutByType(ElementType::User);
        if ($usersFieldLayout) {
            $sources['users'] = $usersFieldLayout->id;
        }

        // Solspace Calendar
        $solspaceCalendarPlugin = craft()->plugins->getPlugin('calendar');
        if ($solspaceCalendarPlugin && $solspaceCalendarPlugin->getDeveloper() === 'Solspace') {
            $solspaceCalendarFieldLayout = craft()->fields->getLayoutByType('Calendar_Event');
            if ($solspaceCalendarFieldLayout) {
                $sources['solspaceCalendar'] = $solspaceCalendarFieldLayout->id;
            }
        }

        // Commerce – TODO
        // $commercePlugin = craft()->plugins->getPlugin('commerce');
        // if ($commercePlugin && $commercePlugin->getDeveloper() === 'Pixel & Tonic') {
        //     // Product types
        //     $productTypes = craft()->commerce_productTypes->getAllProductTypes();
        //     if ($productTypes) {
        //         foreach ($productTypes as $productType) {
        //             $sources['commerceProductType:'.$productType->id] =
        //         }
        //     }
        // }

        // Get all conditionals
        $conditionals = array();
        $conditionalsRecords = Reasons_ConditionalsRecord::model()->findAll();
        if ($conditionalsRecords) {
            foreach ($conditionalsRecords as $conditionalsRecord) {
                $conditionalsModel = Reasons_ConditionalsModel::populateModel($conditionalsRecord);
                if ($conditionalsModel->conditionals && $conditionalsModel->conditionals != '') {
                    $conditionals['fieldLayout:' . $conditionalsModel->fieldLayoutId] = $conditionalsModel->conditionals;
                }
            }
        }

        // Map conditionals to sources
        foreach ($sources as $sourceId => $fieldLayoutId) {
            if (isset($conditionals['fieldLayout:' . $fieldLayoutId])) {
                $r[$sourceId] = $conditionals['fieldLayout:' . $fieldLayoutId];
            }
        }

        return $r;

    }

    /**
     * @return array
     */
    protected function getToggleFieldTypes()
    {

        $stockFieldTypes = array(
            'Lightswitch',
            'Dropdown',
            'Checkboxes',
            'MultiSelect',
            'RadioButtons',
            'Number',
            'PositionSelect',
            'PlainText',
            'Entries',
            'Categories',
            'Tags',
            'Assets',
            'Users',
        );

        $customFieldTypes = array(
            'Calendar_Event',
            'ButtonBox_Buttons',
            'ButtonBox_Colours',
            'ButtonBox_Stars',
            'ButtonBox_TextSize',
            'ButtonBox_Width',
            'PreparseField_Preparse',
        );

        $fieldTypes = array_merge($stockFieldTypes, $customFieldTypes);

        $additionalFieldTypes = craft()->plugins->call('defineAdditionalReasonsToggleFieldTypes', array(), true);

        foreach ($additionalFieldTypes as $pluginHandle => $pluginFieldTypes) {
            $fieldTypes = array_merge($fieldTypes, $pluginFieldTypes);
        }

        return $fieldTypes;

    }

    /*
    *   Returns all toggleable fields
    *
    */
    /**
     * @return array
     */
    protected function getToggleFields()
    {
        $toggleFieldTypes = $this->getToggleFieldTypes();
        $toggleFields = array();
        $fields = craft()->fields->getAllFields();
        foreach ($fields as $field) {
            $fieldType = $field->getFieldType();
            $classHandle = $fieldType && is_object($fieldType) && $fieldType->classHandle ? $fieldType->classHandle : false;
            if (!$classHandle) {
                continue;
            }
            if (in_array($classHandle, $toggleFieldTypes)) {
                $toggleFields[] = array(
                    'id' => $field->id,
                    'handle' => $field->handle,
                    'name' => $field->name,
                    'type' => $classHandle,
                    'contentAttribute' => $fieldType->defineContentAttribute() ?: false,
                    'settings' => $field->settings,
                );
            }
        }
        return $toggleFields;
    }

    /**
     * @return array
     */
    protected function getFieldIds()
    {
        $handles = array();
        $fields = craft()->fields->getAllFields();
        foreach ($fields as $field) {
            $handles[$field->handle] = $field->id;
        }
        return $handles;
    }

    /**
     * @return bool|mixed
     */
    protected function getRevisionManifest()
    {
        $manifestPath = craft()->path->getPluginsPath().'/reasons/resources/rev-manifest.json';
        return (IOHelper::fileExists($manifestPath) && $manifest = IOHelper::getFileContents($manifestPath)) ? JsonHelper::decode($manifest) : false;
    }

    protected function getRevvedResource($src)
    {
      $manifest = $this->getRevisionManifest();
      return $manifest[$src] ?: $src;
    }

    /**
     * @return string
     */
    protected function getCacheKey()
    {
        return $this->_pluginName.'_'.$this->_version.'_'.$this->_schemaVersion;
    }

    /*
    *   Event handlers
    *
    */
    /**
     * @param Event $e
     */
    public function onSaveFieldLayout(Event $e)
    {

        $conditionals = craft()->request->getPost('_reasonsConditionals');

        if ($conditionals) {

          // Get field layout
          $fieldLayout = $e->params['layout'];
          $fieldLayoutId = $fieldLayout->id;

          // Create conditionals model
          $model = new Reasons_ConditionalsModel();
          $model->fieldLayoutId = $fieldLayoutId;
          $model->conditionals = $conditionals;

          // Save it
          craft()->reasons->saveConditionals($model);

        }

        craft()->fileCache->delete($this->getCacheKey());

    }

}
