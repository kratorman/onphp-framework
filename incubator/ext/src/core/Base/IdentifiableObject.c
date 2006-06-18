/* $Id$ */

#include "onphp.h"

#include "core/Base/Identifier.h"
#include "core/Base/IdentifiableObject.h"

PHPAPI zend_class_entry *onphp_ce_IdentifiableObject;

ONPHP_METHOD(IdentifiableObject, wrap)
{
	zval *object, *id;

	MAKE_STD_ZVAL(object);

	object->value.obj = onphp_empty_object_new(onphp_ce_IdentifiableObject TSRMLS_CC);
	Z_TYPE_P(object) = IS_OBJECT;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "z", &id) == FAILURE) {
		WRONG_PARAM_COUNT;
	}

	ONPHP_UPDATE_PROPERTY(object, "id", id);
	
	RETURN_ZVAL(object, 1, 1);
}

ONPHP_METHOD(IdentifiableObject, getId)
{
	zval *this = getThis(), *id;

	onphp_identifiable_object *object = (onphp_identifiable_object *) zend_object_store_get_object(
		this TSRMLS_CC
	);

	id = ONPHP_READ_PROPERTY(this, "id");

	if (
		Z_TYPE_P(id) == IS_OBJECT
		&& instanceof_function(Z_OBJCE_P(id), onphp_ce_Identifier TSRMLS_CC)
	) {
		if (
			zval_is_true(ONPHP_READ_PROPERTY(id, "final"))
		) {
			id = ONPHP_READ_PROPERTY(id, "id");
		}
	}

	RETURN_ZVAL(id, 1, 0);
}

ONPHP_METHOD(IdentifiableObject, setId)
{
	zval *this = getThis(), *id;
	
	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "z", &id) == FAILURE) {
		WRONG_PARAM_COUNT;
	}

	ONPHP_UPDATE_PROPERTY(this, "id", id);

	RETURN_ZVAL(this, 1, 0);
}

zend_function_entry onphp_funcs_IdentifiableObject[] = {
	ONPHP_ME(IdentifiableObject, getId,	NULL, ZEND_ACC_PUBLIC)
	ONPHP_ME(IdentifiableObject, setId,	arginfo_one, ZEND_ACC_PUBLIC)
	ONPHP_ME(IdentifiableObject, wrap,	arginfo_one, ZEND_ACC_PUBLIC | ZEND_ACC_STATIC)
	{NULL, NULL, NULL}
};
