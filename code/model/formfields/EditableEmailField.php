<?php
/**
 * EditableEmailField
 *
 * Allow users to define a validating editable email field for a UserDefinedForm
 *
 * @package userforms
 */

class EditableEmailField extends EditableFormField {
	
	static $singular_name = 'Email Field';
	
	static $plural_name = 'Email Fields';
	
	public function getFormField() {

        $field = new EmailField($this->Name, $this->Title);
        $field->setSmallFieldHolderTemplate("FormField_Holder");
        return $field;
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
		return array(
			'email' => true
		);
	}
}