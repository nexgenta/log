<?php

/* Eregansu Logging
 *
 * Copyright 2009 Mo McRoberts.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The names of the author(s) of this software may not be used to endorse
 *    or promote products derived from this software without specific prior
 *    written permission.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES, 
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY 
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.  IN NO EVENT SHALL
 * AUTHORS OF THIS SOFTWARE BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED
 * TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
 * PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF 
 * LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING 
 * NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS 
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

require_once(dirname(__FILE__) . '/model.php');

class LoggerCLI extends CommandLine
{
	protected $pri = 'user.notice';
	protected $tag = 'logger'; 
	protected $filename = null;
	protected $text = null;
	protected $stderr = false;
	protected $syslog = true;
	protected $facilities = array('auth' => LOG_AUTH, 'authpriv' => LOG_AUTHPRIV, 'cron' => LOG_CRON, 'daemon' => LOG_DAEMON, 'kern' => LOG_KERN, 'lpr' => LOG_LPR, 'mail' => LOG_MAIL, 'news' => LOG_NEWS, 'syslog' => LOG_SYSLOG, 'user' => LOG_USER, 'uucp' => LOG_UUCP, 'local0' => LOG_LOCAL0, 'local1' => LOG_LOCAL1, 'local2' => LOG_LOCAL2, 'local3' => LOG_LOCAL3, 'local4' => LOG_LOCAL4, 'local5' => LOG_LOCAL5, 'local6' => LOG_LOCAL6, 'local7' => LOG_LOCAL7);
	protected $priorities = array('emerg' => LOG_EMERG, 'alert' => LOG_ALERT, 'crit' => LOG_CRIT, 'err' => LOG_ERR, 'warning' => LOG_WARNING, 'notice' => LOG_NOTICE, 'info' => LOG_INFO, 'debug' => LOG_DEBUG);
	
	protected function getObject()
	{
		$text = array();
		while(($arg = array_shift($this->request->params)) !== null)
		{
			$arg = trim($arg);
			if($arg[0] == '-')
			{
				if($arg[1] == 'f' || $arg[1] == 'p' || $arg[1] == 't')
				{
					if(strlen($arg) > 2)
					{
						$argval = trim(substr($arg, 2));
					}
					else
					{
						$argval = array_shift($this->request->params);
					}
					if(!strlen($argval))
					{
						return $this->error(Error::BAD_REQUEST, null, null, 'Option “-' . $arg[1] . '” requires an argument');
					}
				}
				else
				{
					$argval = null;
				}
				switch($arg[1])
				{
					case 'x':
						$this->syslog = false;
						break;
					case 's':
						$this->stderr = true;
						break;
					case 't':
						$this->tag = $argval;
						break;
					case 'p':
						$this->pri = $argval;
						break;
					case 'f':
						$this->filename = $argval;
						break;
					case '-':
						while(($t = array_shift($this->request->params)) !== null)
						{
							$text[] = $t;
						}
						break;
					default:
						return $this->error(Error::BAD_REQUEST, null, null, 'Unrecognised option “' . $arg . '”');
				}
			}
			else
			{
				$text[] = $t;
			}
		}
		$this->text = implode(' ', $text);
		$this->pri = explode('.', strtolower($this->pri), 2);
		if(count($this->pri) == 1)
		{
			$this->pri = array(LOG_USER, $this->pri[0]);
		}
		if(!is_numeric($this->pri[0]))
		{
			if(isset($this->facilities[$this->pri[0]]))
			{
				$this->pri[0] = $this->facilities[$this->pri[0]];
			}
			else
			{
				return $this->error(Error::BAD_REQUEST, null, null, 'Facility “' . $this->pri[0] . '” is not recognised');
			}
		}
		if(!is_numeric($this->pri[1]))
		{
			if(isset($this->priorities[$this->pri[1]]))
			{
				$this->pri[1] = $this->priorities[$this->pri[1]];
			}
			else
			{
				return $this->error(Error::BAD_REQUEST, null, null, 'Priority “' . $this->pri[1] . '” is not recognised');
			}
		}
		return true;
	}
	
	public function main($args)
	{
		Logger::$stderr = array();
		if($this->stderr) Logger::$stderr[] = $this->pri[1];
		Logger::$syslog = array();
		Logger::$syslogFacility = $this->pri[0];
		Logger::$backtrace = array(); /* No point in backtracing */
		if($this->syslog) Logger::$syslog[] = $this->pri[1];
		if($this->filename)
		{
			$f = fopen($this->filename, 'r');
		}
		else if(strlen($this->text))
		{
			Logger::log($this->text, $this->pri[1], $this->tag, $this->request);
			return;
		}
		else
		{
			$f = fopen('php://stdin', 'r');
		}
		if(!$f)
		{
			return false;
		}
		while(!feof($f))
		{
			$line = trim(fgets($f));
			if(strlen($line))
			{			
				Logger::log($line, $this->pri[1], $this->tag, $this->request);
			}
		}
		fclose($f);
	}
}
