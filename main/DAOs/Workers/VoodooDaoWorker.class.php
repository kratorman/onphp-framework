<?php
/***************************************************************************
 *   Copyright (C) 2006-2007 by Konstantin V. Arkhipov                     *
 *                                                                         *
 *   This program is free software; you can redistribute it and/or modify  *
 *   it under the terms of the GNU Lesser General Public License as        *
 *   published by the Free Software Foundation; either version 3 of the    *
 *   License, or (at your option) any later version.                       *
 *                                                                         *
 ***************************************************************************/
/* $Id$ */

	/**
	 * Transparent though quite obscure and greedy DAO worker.
	 * 
	 * @warning Do not ever think about using it on production systems, unless
	 * you're fully understand every line of code here.
	 * 
	 * @magic you'll probably want to tweak your
	 * sysctl when using MessageSegmentHandler:
	 * 
	 * kernel.msgmni = (total number of DAOs + 2)
	 * kernel.msgmnb = 32767
	 * 
	 * @see CommonDaoWorker for manual-caching one.
	 * @see SmartDaoWorker for less obscure, but locking-based worker.
	 * 
	 * @ingroup DAOs
	**/
	final class VoodooDaoWorker extends TransparentDaoWorker
	{
		protected $classKey = null;
		protected $handler = null;
		
		// will trigger auto-detect
		private static $defaultHandler = null;
		
		public static function setDefaultHandler($handler)
		{
			Assert::isTrue(class_exists($handler, true));
			
			self::$defaultHandler = $handler;
		}
		
		public function __construct(GenericDAO $dao)
		{
			parent::__construct($dao);

			if (($cache = Cache::me()) instanceof WatermarkedPeer)
				$watermark = $cache->mark($this->className)->getActualWatermark();
			else
				$watermark = null;
			
			// calling parent just to skip premature getPrecision() calling
			$this->classKey = parent::keyToInt($watermark.$this->className);
			
			$this->handler = $this->spawnHandler($this->classKey);
		}
		
		/// cachers
		//@{
		protected function cacheByQuery(
			SelectQuery $query, /* Identifiable */ $object
		)
		{
			$queryId = $query->getId();
			
			$key = $this->className.self::SUFFIX_QUERY.$queryId;
			
			if ($this->handler->touch($this->keyToInt($key)))
				Cache::me()->mark($this->className)->
					add($key, $object, Cache::EXPIRES_FOREVER);
			
			return $object;
		}
		
		protected function cacheListByQuery(
			SelectQuery $query,
			/* array */ $array
		)
		{
			if ($array !== Cache::NOT_FOUND) {
				Assert::isArray($array);
				Assert::isTrue(current($array) instanceof Identifiable);
			}
			
			$cache = Cache::me();
			
			$key = $this->className.self::SUFFIX_LIST.$query->getId();
			
			if ($this->handler->touch($this->keyToInt($key))) {
				
				$cache->mark($this->className)->
					add($key, $array, Cache::EXPIRES_FOREVER);
				
				if ($array !== Cache::NOT_FOUND)
					foreach ($array as $key => $object) {
						if (
							!$this->handler->ping(
								$this->keyToInt(
									$this->className.'_'.$object->getId()
								)
							)
						) {
							$this->cacheById($object);
						}
					}
			}

			return $array;
		}
		//@}

		/// uncachers
		//@{
		public function uncacheLists()
		{
			return $this->handler->drop();
		}
		//@}
		
		/// internal helpers
		//@{
		protected function gentlyGetByKey($key)
		{
			if ($this->handler->ping($this->keyToInt($key)))
				return Cache::me()->mark($this->className)->get($key);
			else {
				Cache::me()->mark($this->className)->delete($key);
				return null;
			}
		}
		
		protected function spawnHandler($classKey)
		{
			if (!self::$defaultHandler) {
				if (extension_loaded('sysvshm')) {
					$handlerName = 'SharedMemorySegmentHandler';
				} elseif (extension_loaded('sysvmsg')) {
					$handlerName = 'MessageSegmentHandler';
				} else {
					if (extension_loaded('eaccelerator')) {
						$handlerName = 'eAcceleratorSegmentHandler';
					} elseif (extension_loaded('apc')) {
						$handlerName = 'ApcSegmentHandler';
					} elseif (extension_loaded('xcache')) {
						$handlerName = 'XCacheSegmentHandler';
					} else {
						$handlerName = 'FileSystemSegmentHandler';
					}
				}
			} else {
				$handlerName = self::$defaultHandler;
			}
			
			if (!self::$defaultHandler)
				self::$defaultHandler = $handlerName;
			
			return new self::$defaultHandler($classKey);
		}
		
		protected function keyToInt($key)
		{
			return hexdec(substr(md5($key), 0, $this->handler->getPrecision())) + 1;
		}
		//@}
	}
?>