<?php

/**
 * CargoFieldDescription - holds the attributes of a single field as defined
 * in the #cargo_declare parser function.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */
class CargoFieldDescription {
	public $mType;
	public $mSize;
	public $mDependentOn = array();
	public $mIsList = false;
	private $mDelimiter;
	public $mAllowedValues = null;
	public $mIsMandatory = false;
	public $mIsUnique = false;
	public $mRegex = null;
	public $mIsHidden = false;
	public $mIsHierarchy = false;
	public $mHierarchyStructure = null;
	public $mOtherParams = array();

	/**
	 * Initializes from a string within the #cargo_declare function.
	 *
	 * @param string $fieldDescriptionStr
	 * @return \CargoFieldDescription
	 */
	static function newFromString( $fieldDescriptionStr ) {
		$fieldDescription = new CargoFieldDescription();

		if ( strpos( strtolower( $fieldDescriptionStr ), 'list' ) === 0 ) {
			$matches = array();
			$foundMatch = preg_match( '/[Ll][Ii][Ss][Tt] \((.*)\) [Oo][Ff] (.*)/is', $fieldDescriptionStr, $matches );
			if ( !$foundMatch ) {
				// Return a true error message here?
				return null;
			}
			$fieldDescription->mIsList = true;
			$fieldDescription->mDelimiter = $matches[1];
			$fieldDescriptionStr = $matches[2];
		}

		// There may be additional parameters, in/ parentheses.
		$matches = array();
		$foundMatch2 = preg_match( '/([^(]*)\s*\((.*)\)/s', $fieldDescriptionStr, $matches );
		$allowedValuesParam = "";
		if ( $foundMatch2 ) {
			$fieldDescriptionStr = trim( $matches[1] );
			$extraParamsString = $matches[2];
			$extraParams = explode( ';', $extraParamsString );
			foreach ( $extraParams as $extraParam ) {
				$extraParamParts = explode( '=', $extraParam, 2 );
				if ( count( $extraParamParts ) == 1 ) {
					$paramKey = strtolower( trim( $extraParamParts[0] ) );
					if ( $paramKey == 'hierarchy' ) {
						$fieldDescription->mIsHierarchy = true;
					}
					$fieldDescription->mOtherParams[$paramKey] = true;
				} else {
					$paramKey = strtolower( trim( $extraParamParts[0] ) );
					$paramValue = trim( $extraParamParts[1] );
					if ( $paramKey == 'allowed values' ) {
						// we do not assign allowed values to fieldDescription here,
						// because we don't know yet if it's a hierarchy or an enumeration
						$allowedValuesParam = $paramValue;
					} elseif ( $paramKey == 'size' ) {
						$fieldDescription->mSize = $paramValue;
					} elseif ( $paramKey == 'dependent on' ) {
						$fieldDescription->mDependentOn = array_map( 'trim', explode( ',', $paramValue ) );
					} else {
						$fieldDescription->mOtherParams[$paramKey] = $paramValue;
					}
				}
			}
			if ( $allowedValuesParam !== "" ) {
				$allowedValuesArray = array();
				if ( $fieldDescription->mIsHierarchy == true ) {
					// $paramValue contains "*" hierarchy structure
					CargoUtils::validateHierarchyStructure( trim( $allowedValuesParam ) );
					$fieldDescription->mHierarchyStructure = trim( $allowedValuesParam );
					// now make the allowed values param similar to the syntax
					// used by other fields
					$hierarchyNodesArray = explode( "\n", $allowedValuesParam );
					foreach ( $hierarchyNodesArray as $node ) {
						// Remove prefix of multiple "*"
						$allowedValuesArray[] = trim( preg_replace( '/^[*]*/', '', $node ) );
					}
				} else {
					// Replace the comma/delimiter
					// substitution with a character
					// that has no chance of being
					// included in the values list -
					// namely, the ASCII beep.

					// The delimiter can't be a
					// semicolon, because that's
					// already used to separate
					// "extra parameters", so just
					// hardcode it to a semicolon.
					$delimiter = ',';
					$allowedValuesStr = str_replace( "\\$delimiter", "\a", $allowedValuesParam );
					$allowedValuesTempArray = explode( $delimiter, $allowedValuesStr );
					foreach ( $allowedValuesTempArray as $i => $value ) {
						if ( $value == '' ) {
							continue;
						}
						// Replace beep back with delimiter, trim.
						$value = str_replace( "\a", $delimiter, trim( $value ) );
						$allowedValuesArray[] = $value;
					}
				}
				$fieldDescription->mAllowedValues = $allowedValuesArray;
			}

		}

		// What's left will be the type, hopefully.
		// Allow any capitalization of the type.
		$type = ucfirst( strtolower( $fieldDescriptionStr ) );
		// The 'URL' type has special capitalization.
		if ( $type == 'Url' ) {
			$type = 'URL';
		}
		$fieldDescription->mType = $type;

		// Validation.
		if ( $fieldDescription->mType == 'Text' && array_key_exists( 'unique', $fieldDescription->mOtherParams ) ) {
			throw new MWException( "'unique' is not allowed for fields of type 'Text'." );
		}

		return $fieldDescription;
	}

	/**
	 *
	 * @param array $descriptionData
	 * @return \CargoFieldDescription
	 */
	static function newFromDBArray( $descriptionData ) {
		$fieldDescription = new CargoFieldDescription();
		foreach ( $descriptionData as $param => $value ) {
			if ( $param == 'type' ) {
				$fieldDescription->mType = $value;
			} elseif ( $param == 'size' ) {
				$fieldDescription->mSize = $value;
			} elseif ( $param == 'dependent on' ) {
				$fieldDescription->mDependentOn = $value;
			} elseif ( $param == 'isList' ) {
				$fieldDescription->mIsList = true;
			} elseif ( $param == 'delimiter' ) {
				$fieldDescription->mDelimiter = $value;
			} elseif ( $param == 'allowedValues' ) {
				$fieldDescription->mAllowedValues = $value;
			} elseif ( $param == 'mandatory' ) {
				$fieldDescription->mIsMandatory = true;
			} elseif ( $param == 'unique' ) {
				$fieldDescription->mIsUnique = true;
			} elseif ( $param == 'regex' ) {
				$fieldDescription->mRegex = $value;
			} elseif ( $param == 'hidden' ) {
				$fieldDescription->mIsHidden = true;
			} elseif ( $param == 'hierarchy' ) {
				$fieldDescription->mIsHierarchy = true;
			} elseif ( $param == 'hierarchyStructure' ) {
				$fieldDescription->mHierarchyStructure = $value;
			} else {
				$fieldDescription->mOtherParams[$param] = $value;
			}
		}
		return $fieldDescription;
	}

	function getDelimiter() {
		// Make "\n" represent a newline.
		return str_replace( '\n', "\n", $this->mDelimiter );
	}

	function setDelimiter( $delimiter ) {
		$this->mDelimiter = $delimiter;
	}

	function isDateOrDatetime() {
		return in_array( $this->mType, array( 'Date', 'Start date', 'End date', 'Datetime', 'Start datetime', 'End datetime' ) );
	}

	/**
	 *
	 * @return array
	 */
	function toDBArray() {
		$descriptionData = array();
		$descriptionData['type'] = $this->mType;
		if ( $this->mSize != null ) {
			$descriptionData['size'] = $this->mSize;
		}
		if ( $this->mDependentOn != null ) {
			$descriptionData['dependent on'] = $this->mDependentOn;
		}
		if ( $this->mIsList ) {
			$descriptionData['isList'] = true;
		}
		if ( $this->mDelimiter != null ) {
			$descriptionData['delimiter'] = $this->mDelimiter;
		}
		if ( $this->mAllowedValues != null ) {
			$descriptionData['allowedValues'] = $this->mAllowedValues;
		}
		if ( $this->mIsMandatory ) {
			$descriptionData['mandatory'] = true;
		}
		if ( $this->mIsUnique ) {
			$descriptionData['unique'] = true;
		}
		if ( $this->mRegex != null ) {
			$descriptionData['regex'] = $this->mRegex;
		}
		if ( $this->mIsHidden ) {
			$descriptionData['hidden'] = true;
		}
		if ( $this->mIsHierarchy ) {
			$descriptionData['hierarchy'] = true;
			$descriptionData['hierarchyStructure'] = $this->mHierarchyStructure;
		}
		foreach ( $this->mOtherParams as $otherParam => $value ) {
			$descriptionData[$otherParam] = $value;
		}

		return $descriptionData;
	}
}
