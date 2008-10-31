<?php
/***************************************************************************
 *   Copyright (C) 2005-2008 by Sergey S. Sergeev                          *
 *                                                                         *
 *   This program is free software; you can redistribute it and/or modify  *
 *   it under the terms of the GNU Lesser General Public License as        *
 *   published by the Free Software Foundation; either version 3 of the    *
 *   License, or (at your option) any later version.                       *
 *                                                                         *
 ***************************************************************************/
/* $Id$ */

	class RouterTransparentRule extends RouterBaseRule
	{
		protected $urlVariable		= ':';
		protected $urlDelimiter		= '/';
		protected $regexDelimiter	= '#';
		protected $defaultRegex		= null;
		protected $variables		= array();
		protected $parts			= array();
		protected $requirements		= array();
		protected $values			= array();
		protected $wildcardData		= array();
		protected $staticCount		= 0;

		/**
		 * @return RouterTransparentRule
		**/
		public static function create($route /** [,array $defaults] [, array $reqs] **/)
		{
			$list = func_get_args();

			$defaults = array();
			$reqs = array();

			if (!empty($list[1]))
				$defaults = $list[1];

			if (!empty($list[2]))
				$reqs = $list[2];

			return new self($route, $defaults, $reqs);
		}

		public function __construct($route, $defaults = array(), $reqs = array())
		{
			$route = trim($route, $this->urlDelimiter);
			$this->defaults = (array) $defaults;
			$this->requirements = (array) $reqs;

			if ($route != '') {
				foreach (explode($this->urlDelimiter, $route) as $pos => $part) {
					if (substr($part, 0, 1) == $this->urlVariable) {
						$name = substr($part, 1);
						$this->parts[$pos] = (
							isset($reqs[$name])
								? $reqs[$name]
								: $this->defaultRegex
						);

						$this->variables[$pos] = $name;
					} else {
						$this->parts[$pos] = $part;
						if ($part != '*')
							$this->staticCount++;
					}
				}
			}
		}

		/**
		 * @return RouterTransparentRule
		**/
		public function setRequirements(array $reqirements)
		{
			$this->requirements = $reqirements;

			return $this;
		}

		public function getRequirements()
		{
			return $this->requirements;
		}

		/**
		 * Matches a user submitted path with parts defined by a map. Assigns and
		 * returns an array of variables on a successful match.
		 *
		 * @return array|false An array of assigned values or a false on a mismatch
		**/
		public function match(HttpRequest $request)
		{
			$path = $this->processPath($request)->toString();

			$pathStaticCount = 0;
			$values = array();

			$path = trim($path, $this->urlDelimiter);

			if ($path != '') {
				$path = explode($this->urlDelimiter, $path);

				foreach ($path as $pos => $pathPart) {
					if (!array_key_exists($pos, $this->parts)) {
						return false;
					}

					if ($this->parts[$pos] == '*') {
						$count = count($path);
						for($i = $pos; $i < $count; $i += 2) {
							$var = urldecode($path[$i]);
							if (
								!isset($this->wildcardData[$var])
								&& !isset($this->defaults[$var])
								&& !isset($values[$var])
							) {
								$this->wildcardData[$var] =
									(isset($path[$i+1]))
										? urldecode($path[$i+1])
										: null;
							}
						}

						break;
					}

					$name =
						isset($this->variables[$pos])
							? $this->variables[$pos]
							: null;

					$pathPart = urldecode($pathPart);

					if (
						$name === null
						&& $this->parts[$pos] != $pathPart
					) {
						return false;
					}

					if (
						$this->parts[$pos] !== null
						&& !preg_match(
							$this->regexDelimiter
							.'^'.$this->parts[$pos].'$'
							.$this->regexDelimiter.'iu',
							$pathPart
						)
					) {
						return false;
					}

					if ($name !== null) {
						$values[$name] = $pathPart;
					} else {
						$pathStaticCount++;
					}
				}
			}

			if ($this->staticCount != $pathStaticCount)
				return false;

			$return = $values + $this->wildcardData + $this->defaults;

			foreach ($this->variables as $var) {
				if (!array_key_exists($var, $return))
					return false;
			}

			$this->values = $values;

			return $return;
		}

		/**
		 * Assembles user submitted parameters forming a URL path defined by this route
		 *
		 * @param  array $data An array of variable and value pairs used as parameters
		 * @param  boolean $reset Whether or not to set route defaults with those provided in $data
		 * @return string Route path with user submitted parameters
		**/
		public function assemble($data = array(), $reset = false, $encode = false)
		{
			$url = array();
			$flag = false;

			foreach ($this->parts as $key => $part) {
				$name =
					isset($this->variables[$key])
						? $this->variables[$key]
						: null;

				$useDefault = false;
				if (
					isset($name)
					&& array_key_exists($name, $data)
					&& $data[$name] === null
				) {
					$useDefault = true;
				}

				if (isset($name)) {
					if (
						isset($data[$name])
						&& !$useDefault
					) {
						$url[$key] = $data[$name];
						unset($data[$name]);
					} elseif (
						!$reset &&
						!$useDefault
						&& isset($this->values[$name])
					) {
						$url[$key] = $this->values[$name];
					} elseif (
						!$reset
						&& !$useDefault
						&& isset($this->wildcardData[$name])
					) {
						$url[$key] = $this->wildcardData[$name];
					} elseif (isset($this->defaults[$name])) {
						$url[$key] = $this->defaults[$name];
					} else {
						throw new RouterException("{$name} is not specified");
					}
				} elseif ($part != '*') {
					$url[$key] = $part;
				} else {
					if (!$reset)
						$data += $this->wildcardData;

					foreach ($data as $var => $value) {
						if ($value !== null) {
							if (
								isset($this->defaults[$var])
								&& $this->defaults[$var] === $value
							) {
								continue;
							}

							$url[$key++] = $var;
							$url[$key++] = $value;
							$flag = true;
						}
					}
				}
			}

			$return = '';

			foreach (array_reverse($url, true) as $key => $value) {
				if (
					$flag
					|| !isset($this->variables[$key])
					|| $value !== $this->getDefault($this->variables[$key])
				) {
					if ($encode)
						$value = urlencode($value);

					$return =
						$this->urlDelimiter
						.$value
						.$return;

					$flag = true;
				}
			}

			return trim($return, $this->urlDelimiter);
		}
	}
?>