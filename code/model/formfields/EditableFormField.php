<?php
/**
 * Represents the base class of a editable form field 
 * object like {@link EditableTextField}. 
 *
 * @package userforms
 */

class EditableFormField extends DataObject {
	
	static $default_sort = "Sort";
	
	/**
	 * A list of CSS classes that can be added
	 *
	 * @var array
	 */
	public static $allowed_css = array();
	
	static $db = array(
		"Name" => "Varchar",
		"Title" => "Varchar(255)",
		"Default" => "Varchar",
		"Sort" => "Int",
		"Required" => "Boolean",
		"CustomErrorMessage" => "Varchar(255)",
		"CustomRules" => "Text",
		"CustomSettings" => "Text",
		"CustomParameter" => "Varchar(200)"
	);

	static $has_one = array(
		"Parent" => "UserDefinedForm",
	);
	
	static $extensions = array(
		"Versioned('Stage', 'Live')"
	);
	
	/**
	 * @var bool
	 */
	protected $readonly;
	
	/**
	 * Set the visibility of an individual form field
	 *
	 * @param bool
	 */ 
	public function setReadonly($readonly = true) {
		$this->readonly = $readonly;
	}

	/**
	 * Returns whether this field is readonly 
	 * 
	 * @return bool
	 */
	private function isReadonly() {
		return $this->readonly;
	}
	
	/**
	 * Template to render the form field into
	 *
	 * @return String
	 */
	public function EditSegment() {
		return $this->renderWith('EditableFormField');
	}
	
	/**
	 * Return whether a user can delete this form field
	 * based on whether they can edit the page
	 *
	 * @return bool
	 */
	public function canDelete($member = null) {
		return ($this->Parent()->canEdit($member = null) && !$this->isReadonly());
	}
	
	/**
	 * Return whether a user can edit this form field
	 * based on whether they can edit the page
	 *
	 * @return bool
	 */
	public function canEdit($member = null) {
		return ($this->Parent()->canEdit($member = null) && !$this->isReadonly());
	}
	
	/**
	 * Publish this Form Field to the live site
	 * 
	 * Wrapper for the {@link Versioned} publish function
	 */
	public function doPublish($fromStage, $toStage, $createNewVersion = false) {
		$this->publish($fromStage, $toStage, $createNewVersion);
	}
	
	/**
	 * Delete this form from a given stage
	 *
	 * Wrapper for the {@link Versioned} deleteFromStage function
	 */
	public function doDeleteFromStage($stage) {
		$this->deleteFromStage($stage);
	}
	
	/**
	 * checks wether record is new, copied from Sitetree
	 */
	function isNew() {
		if(empty($this->ID)) return true;

		if(is_numeric($this->ID)) return false;

		return stripos($this->ID, 'new') === 0;
	}

	/**
	 * checks if records is changed on stage
	 * @return boolean
	 */
	public function getIsModifiedOnStage() {
		// new unsaved fields could be never be published
		if($this->isNew()) return false;

		$stageVersion = Versioned::get_versionnumber_by_stage('EditableFormField', 'Stage', $this->ID);
		$liveVersion =	Versioned::get_versionnumber_by_stage('EditableFormField', 'Live', $this->ID);

		return ($stageVersion && $stageVersion != $liveVersion);
	}

	
	/**
	 * Show this form on load or not
	 *
	 * @return bool
	 */
	public function getShowOnLoad() {
		return ($this->getSetting('ShowOnLoad') == "Show" || $this->getSetting('ShowOnLoad') == '') ? true : false;
	}
	
	/**
	 * To prevent having tables for each fields minor settings we store it as 
	 * a serialized array in the database. 
	 * 
	 * @return Array Return all the Settings
	 */
	public function getSettings() {
		return (!empty($this->CustomSettings)) ? unserialize($this->CustomSettings) : array();
	}
	
	/**
	 * Set the custom settings for this field as we store the minor details in
	 * a serialized array in the database
	 *
	 * @param Array the custom settings
	 */
	public function setSettings($settings = array()) {
		$this->CustomSettings = serialize($settings);
	}
	
	/**
	 * Set a given field setting. Appends the option to the settings or overrides
	 * the existing value
	 *
	 * @param String key 
	 * @param String value
	 */
	public function setSetting($key, $value) {
		$settings = $this->getSettings();
		$settings[$key] = $value;
		
		$this->setSettings($settings);
	}

	/**
	 * Set the allowed css classes for the extraClass custom setting
	 * 
	 * @param array The permissible CSS classes to add
	 */
	public function setAllowedCss(array $allowed) {
		if (is_array($allowed_css)){
			foreach ($allowed_css as $k => $v){
				self::$allowed_css[$k] = (!is_null($v)) ? $v : $k;
			}
		}
	}

	/**
	 * Return just one custom setting or empty string if it does
	 * not exist
	 *
	 * @param String Value to use as key
	 * @return String
	 */
	public function getSetting($setting) {
		$settings = $this->getSettings();
		if(isset($settings) && count($settings) > 0) {
			if(isset($settings[$setting])) {
				return $settings[$setting];
			}
		}
		return '';
	}
	
	/**
	 * Get the path to the icon for this field type, relative to the site root.
	 *
	 * @return string
	 */
	public function getIcon() {
		return 'userforms/images/' . strtolower($this->class) . '.png';
	}
	
	/**
	 * Return whether or not this field has addable options
	 * such as a dropdown field or radio set
	 *
	 * @return bool
	 */
	public function getHasAddableOptions() {
		return false;
	}
	
	/**
	 * Return whether or not this field needs to show the extra
	 * options dropdown list
	 * 
	 * @return bool
	 */
	public function showExtraOptions() {
		return true;
	}
	
	/**
	 * Return the custom validation fields for this field for the CMS
	 *
	 * @return array
	 */
	public function Dependencies() {
		return ($this->CustomRules) ? unserialize($this->CustomRules) : array();
	}
	
	/**
	 * Return the custom validation fields for the field
	 * 
	 * @return DataObjectSet
	 */
	public function CustomRules() {
		$output = new ArrayList();
		$fields = $this->Parent()->Fields();

		// check for existing ones
		if($rules = $this->Dependencies()) {
			foreach($rules as $rule => $data) {

				// recreate all the field object to prevent caching
				$outputFields = new ArrayList();
				
				foreach($fields as $field) {
					$new = clone $field;

					$new->isSelected = ($new->Name == $data['ConditionField']) ? true : false;
					$outputFields->push($new);
				}
				
				$output->push(new ArrayData(array(
					'FieldName' => $this->getFieldName(),
					'Display' => $data['Display'],
					'Fields' => $outputFields,
					'ConditionField' => $data['ConditionField'],
					'ConditionOption' => $data['ConditionOption'],
					'Value' => $data['Value']
				)));
			}
		}
	
		return $output;
	}

	/**
	 * Title field of the field in the backend of the page
	 *
	 * @return TextField
	 */
	public function TitleField() {
		$label = _t('EditableFormField.ENTERQUESTION', 'Enter Question');
		
		$field = new TextField('Title', $label, $this->getField('Title'));
		$field->setName($this->getFieldName('Title'));

		if(!$this->canEdit()) {
			return $field->performReadonlyTransformation();
		}

		return $field;
	}

	/** Returns the Title for rendering in the front-end (with XML values escaped) */
	public function getTitle() {
		return Convert::raw2att($this->getField('Title'));
	}

	/**
	 * Return the base name for this form field in the 
	 * form builder. Optionally returns the name with the given field
	 *
	 * @param String Field Name
	 *
	 * @return String
	 */
	public function getFieldName($field = false) {
		return ($field) ? "Fields[".$this->ID."][".$field."]" : "Fields[".$this->ID."]";
	}
	
	/**
	 * Generate a name for the Setting field
	 *
	 * @param String name of the setting
	 * @return String
	 */
	public function getSettingName($field) {
		$name = $this->getFieldName('CustomSettings');
		
		return $name . '[' . $field .']';
	}
	
	/**
	 * How to save the data submitted in this field into
	 * the database object which this field represents.
	 *
	 * Any class's which call this should also call 
	 * {@link parent::populateFromPostData()} to ensure 
	 * that this method is called
	 *
	 * @access public
	 */
	public function populateFromPostData($data) {
		$this->Title 		= (isset($data['Title'])) ? $data['Title']: "";
		$this->Default 		= (isset($data['Default'])) ? $data['Default'] : "";
		$this->Sort 		= (isset($data['Sort'])) ? $data['Sort'] : null;
		$this->Required 	= !empty($data['Required']) ? 1 : 0;
		$this->Name 		= $this->class.$this->ID;
		$this->CustomRules	= "";
		$this->CustomErrorMessage	= (isset($data['CustomErrorMessage'])) ? $data['CustomErrorMessage'] : "";
		$this->CustomSettings		= "";
		
		// custom settings
		if(isset($data['CustomSettings'])) {
			$this->setSettings($data['CustomSettings']);
		}

		// custom validation
		if(isset($data['CustomRules'])) {
			$rules = array();
			
			foreach($data['CustomRules'] as $key => $value) {

				if(is_array($value)) {
					$fieldValue = (isset($value['Value'])) ? $value['Value'] : '';
					
					if(isset($value['ConditionOption']) && $value['ConditionOption'] == "Blank" || $value['ConditionOption'] == "NotBlank") {
						$fieldValue = "";
					}
					
					$rules[] = array(
						'Display' => (isset($value['Display'])) ? $value['Display'] : "",
						'ConditionField' => (isset($value['ConditionField'])) ? $value['ConditionField'] : "",
						'ConditionOption' => (isset($value['ConditionOption'])) ? $value['ConditionOption'] : "",
						'Value' => $fieldValue
					);
				}
			}
			
			$this->CustomRules = serialize($rules);
		}
		
		$this->write();
	}
	 
	/**
	 * Implement custom field Configuration on this field. Includes such things as
	 * settings and options of a given editable form field
	 *
	 * @return FieldSet
	 */
    /*
	public function getFieldConfiguration() {
		$extraClass = ($this->getSetting('ExtraClass')) ? $this->getSetting('ExtraClass') : '';

		if (is_array(self::$allowed_css) && !empty(self::$allowed_css)) {
			foreach(self::$allowed_css as $k => $v) {
				if (!is_array($v)) $cssList[$k]=$v;
				elseif ($k == $this->ClassName()) $cssList = array_merge($cssList, $v);
			}
			
			$ec = new DropdownField(
				$this->getSettingName('ExtraClass'), 
				_t('EditableFormField.EXTRACLASSA', 'Extra Styling/Layout'), 
				$cssList, $extraClass
			);
			
		}
		else {
			$ec = new TextField(
				$this->getSettingName('ExtraClass'), 
				_t('EditableFormField.EXTRACLASSB', 'Extra css Class - separate multiples with a space'), 
				$extraClass
			);
		}
		
		$right = new TextField(
			$this->getSettingName('RightTitle'), 
			_t('EditableFormField.RIGHTTITLE', 'Right Title'), 
			$this->getSetting('RightTitle')
		);
			
		return new FieldList(
			$ec,
			$right
		);
	}
	*/

    //CUSTOM - adding from previous
    /**
     * Generate a name for the Setting field
     *
     * @param String name of the setting
     * @return String
     */
    public function getSettingFieldName($field) {
        $name = $this->getFieldName('CustomSettings');

        return $name . '[' . $field .']';
    }
    // CUSTOM
    //CUSTOM - rewritten, RightTitle removed

    public function getFieldConfiguration() {

        //Internal field is the UserDefinedForms generated field name, eg; EditableRadioField148
        //Output field name is the 'nicer' name used for output to webservices and xml, eg; CurrentWorkplaceName

        $internalField = new LiteralField(
            'internalField',
            '<div class="field text"><label class="left">Internal Field Name: <strong>' . $this->Name . '</strong></label></div>'
        );

        $outputFieldName = $this->getSetting('OutputFieldName');

        //default to the fields Name if not set
        if ($outputFieldName == '') {
            $outputFieldName = $this->Name;
        }

        $outputField = new TextField(
            $this->getSettingFieldName('OutputFieldName'),
            'Output Field Name',
            $outputFieldName
        );

        return new FieldList(
            $internalField,
            $outputField
        );
    }
    //CUSTOM
	/**
	 * Append custom validation fields to the default 'Validation' 
	 * section in the editable options view
	 * 
	 * @return FieldSet
	 */
	public function getFieldValidationOptions() {
		$fields = new FieldList(
			new CheckboxField($this->getFieldName('Required'), _t('EditableFormField.REQUIRED', 'Is this field Required?'), $this->Required),
			new TextField($this->getFieldName('CustomErrorMessage'), _t('EditableFormField.CUSTOMERROR','Custom Error Message'), $this->CustomErrorMessage)
		);
		
		if(!$this->canEdit()) {
			foreach($fields as $field) {
				$field->performReadonlyTransformation();
			}
		}
		
		return $fields;
	}
	
	/**
	 * Return a FormField to appear on the front end. Implement on 
	 * your subclass
	 *
	 * @return FormField
	 */
	public function getFormField() {
		user_error("Please implement a getFormField() on your EditableFormClass ". $this->ClassName, E_USER_ERROR);
	}
	
	/**
	 * Return the instance of the submission field class
	 *
	 * @return SubmittedFormField
	 */
	public function getSubmittedFormField() {
		return new SubmittedFormField();
	}
	
	
	/**
	 * Show this form field (and its related value) in the reports and in emails.
	 *
	 * @return bool
	 */
	public function showInReports() {
		return true;
	}
 
	/**
	 * Return the validation information related to this field. This is 
	 * interrupted as a JSON object for validate plugin and used in the 
	 * PHP. 
	 *
	 * @see http://docs.jquery.com/Plugins/Validation/Methods
	 * @return Array
	 */
	public function getValidation() {
		return array();
	}
	
	/**
	 * Return the error message for this field. Either uses the custom
	 * one (if provided) or the default SilverStripe message
	 *
	 * @return Varchar
	 */
	public function getErrorMessage() {
		$title = strip_tags("'". ($this->Title ? $this->Title : $this->Name) . "'");
		$standard = sprintf(_t('Form.FIELDISREQUIRED', '%s is required').'.', $title);
		
		$errorMessage = ($this->CustomErrorMessage) ? $this->CustomErrorMessage : $standard;
		
		return DBField::create_field('Varchar', $errorMessage);
	}

    //CUSTOM
    //This is used to determine if the parent is a UserDefinedForm (default) or a UserDefinedFieldSet.
    //It overrides the default Parent call as its set to UserDefinedForm ^ above
    public function Parent() {
        $parent = null;
        $parentClass = "UserDefinedForm";

        //If ParentClass is set, use that instead of the default above
        if (!is_null($this->ParentClass)) {
            $parentClass = $this->ParentClass;
        }

        //If ParentID is not null, fetch the instance for the given class & ID from the database
        if (!is_null($this->ParentID)) {
            $parent = DataObject::get_by_id($parentClass, $this->ParentID);
        }

        //For some odd reason, directly calling the default Parent() generates a blank,
        //new instance if there isn't one - duplicate this functionality here
        if (is_null($parent) || $parent==false) {
            $parent = new $parentClass;
        }
        return $parent;
    }


    //Returns the "OutputFieldName" field prefixed with the UserDefinedFieldSet Code ,
    //eg; OutputFieldName = "SupervisorName",
    //    Code = "ECEWorkplace"
    //    output will be: "ECEWorkplace_SupervisorName"
    //
    // This is done to ensure that the name is namespaced, unique and nice for output
    // to xml and/or webservices
    public function OutputName() {
        $name = $this->getField("Name");
        $parent = $this->Parent();

        if (!is_null($parent)) {
            $outputFieldName = $this->getSetting('OutputFieldName');

            if ($outputFieldName) {
                $name = $parent->Code . "_" . $outputFieldName;
            }
        }

        return $name;
    }

    //CUSTOM


    //CUSTOM
    //Return the display value for this field
    //Override these if your editable form field has more complex rules
    public function getDisplayValueFromData($data) {

        $value = (isset($data[$this->Name])) ? $data[$this->Name] : null;

        $this->extend('customiseDisplayValueFromData',$data,$value);

        return Convert::raw2xml($value);
    }

    //return the xml value for this field (by default, the same as the display)
    public function getXMLValueFromData($data) {

        $value = (isset($data[$this->Name])) ? $data[$this->Name] : null;

        $this->extend('customiseXMLValueFromData',$data,$value);

        return Util::xmlEncodeArray($value);
    }


    public function hasReminder()
    {
        $result=false;

        $this->extend('customiseHasReminderValue',$result);

        return $result;
    }

    public function shouldShowOnSummaryPage()
    {
        $result=true;

        $this->extend('customiseShouldShowOnSummaryPageValue',$result);

        return $result;
    }
    //CUSTOM

}
