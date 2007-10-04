<?php
/***************************************************************************
 *   Copyright (C) 2007 by Dmitry E. Demidov                               *
 *                                                                         *
 *   This program is free software; you can redistribute it and/or modify  *
 *   it under the terms of the GNU Lesser General Public License as        *
 *   published by the Free Software Foundation; either version 3 of the    *
 *   License, or (at your option) any later version.                       *
 *                                                                         *
 ***************************************************************************/
/* $Id$ */

	/**
	 * @ingroup Utils
	**/
	final class ClassUtils extends StaticFactory
	{
		/* void */ public static function copyProperties($source, $destination)
		{
			Assert::isTrue(get_class($source) == get_class($destination));
			
			$class = new ReflectionClass($source);
			
			foreach ($class->getProperties() as $property) {
				$name = ucfirst($property->getName());
				$getter = 'get'.$name;
				$setter = 'set'.$name;
				
				if (
					($class->hasMethod($getter))
					&& ($class->hasMethod($setter))
				) {
					
					$sourceValue = $source->$getter();
					
					if ($sourceValue === null) {
						
						$setMethood = $class->getMethod($setter);
						$parameterList = $setMethood->getParameters();
						$firstParameter = $parameterList[0];
						
						if ($firstParameter->allowsNull())
							$destination->$setter($sourceValue);
						
					} else {
						$destination->$setter($sourceValue);
					}
				}
			}
		}
		
		/* void */ public static function copyNotNullProperties($source, $destination)
		{
			Assert::isTrue(get_class($source) == get_class($destination));
			
			$class = new ReflectionClass($source);
			
			foreach ($class->getProperties() as $property) {
				$name = ucfirst($property->getName());
				$getter = 'get'.$name;
				$setter = 'set'.$name;
				
				if (
					($class->hasMethod($getter))
					&& ($class->hasMethod($setter))
				) {
					$value = $source->$getter();
					if ($value !== null)
						$destination->$setter($value);
				}
			}
		}
		
		/* void */ public static function fillNullProperties($source, $destination)
		{
			Assert::isTrue(get_class($source) == get_class($destination));
			
			$class = new ReflectionClass($source);
			
			foreach ($class->getProperties() as $property) {
				$name = ucfirst($property->getName());
				$getter = 'get'.$name;
				$setter = 'set'.$name;
				
				if (
					($class->hasMethod($getter))
					&& ($class->hasMethod($setter))
				) {
					$destinationValue = $destination->$getter();
					$sourceValue = $source->$getter();
					
					if (
						($destinationValue === null)
						&& ($sourceValue !== null) 
					) {
						$destination->$setter($sourceValue);
					}
				}
			}
		}
		
		public static function isClassName($className)
		{
			return
				preg_match(
					'/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', 
					$className
				);
		}
		
		public static function isInstanceOf($object, $class)
		{
			if (is_object($class)) {
				$className = get_class($class);
			} elseif (is_string($class)) {
				$className = $class;
			} else {
				throw new WrongArgumentException('strange class given');
			}
			
			if (
				is_string($object) 
				&& self::isClassName($object)
			) {
				if ($object == $className)
					return true;
				elseif (is_subclass_of($object, $className))
					return true;
				else
					return in_array(
						$class, 
						class_implements($object, true)
					);
					
			} elseif (is_object($object)) {
				return $object instanceof $className;
				
			} else {
				throw new WrongArgumentException('strange object given');
			}
		}
		
		public static function callStaticMethod($methodSignature /* , ... */)
		{
			$agruments = func_get_args();
			array_shift($agruments);
			
			return
				call_user_func_array(
					self::checkStaticMethod($methodSignature),
					$agruments
				);
		}
		
		public static function checkStaticMethod($methodSignature)
		{
			$nameParts = explode('::', $methodSignature, 2);
			
			if (count($nameParts) != 2)
				throw new WrongArgumentException('incorrect method signature');
			
			list($className, $methodName) = $nameParts;
			
			try {
				$class = new ReflectionClass($className);
			} catch (ReflectionException $e) {
				throw new ClassNotFoundException($className);
			}
			
			Assert::isTrue(
				$class->hasMethod($methodName),
				"knows nothing about '{$className}::{$methodName}' method"
			);
			
			$method = $class->getMethod($methodName);
			
			Assert::isTrue(
				$method->isStatic(),
				"method is not static '{$className}::{$methodName}'"
			);
			
			Assert::isTrue(
				$method->isPublic(),
				"method is not public '{$className}::{$methodName}'"
			);
			
			return $nameParts;
		}
	}
?>