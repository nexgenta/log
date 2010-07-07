<?php

uses('model');

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

global $LOG_SYSLOG, $LOG_SYSLOG_FACILITY, $LOG_BACKTRACE, $LOG_STDERR;

if(!defined('LOG_IRI')) define('LOG_IRI', null);

if(!isset($LOG_SYSLOG)) $LOG_SYSLOG = array(LOG_CRIT, LOG_ALERT, LOG_EMERG);
if(!isset($LOG_SYSLOG_FACILITY)) $LOG_SYSLOG_FACILITY = LOG_INFO;
if(!isset($LOG_BACKTRACE)) $LOG_BACKTRACE = array(LOG_CRIT, LOG_ALERT, LOG_EMERG, LOG_DEBUG);
if(!isset($LOG_STDERR)) $LOG_STDERR = array(LOG_CRIT, LOG_ALERT, LOG_EMERG);

class Logger extends Model
{
	protected static $log;
	protected static $pid;
	protected static $clusterName;
	protected static $instanceName;
	protected static $ferr;
	protected static $clusterSpec;
	
	protected static $priorities = array(
		LOG_EMERG => 'EMERG',
		LOG_ALERT => 'ALERT',
		LOG_CRIT => 'CRIT',
		LOG_ERR => 'ERR',
		LOG_WARNING => 'WARNING',
		LOG_NOTICE => 'NOTICE',
		LOG_INFO => 'INFO',
		LOG_DEBUG => 'DEBUG',
	);
	
	/* Priorities which should use syslog() */
	public static $syslog;
	/* Facility which should be used by syslog */
	public static $syslogFacility;
	/* Priorities which should include a backtrace */
	public static $backtrace;
	/* Priorities which should be written to stderr */
	public static $stderr;
	
	public static function log($message, $priority = LOG_INFO, $ident = null, $request = null)
	{
		if(!self::$log) self::$log = self::getInstance();
		$userScheme = null;
		$userUuid = null;
		if(isset($request->session->user['scheme']))
		{
			$userScheme = $request->session->user['scheme'];
			$userUuid = $request->session->user['uuid'];
		}
		self::$log->logEvent($message, $priority, $ident, $userScheme, $userUuid);
	}
	
	public static function getInstance($args = null)
	{
		if(!isset($args['db'])) $args['db'] = LOG_IRI;
		if(!isset($args['class'])) $args['class'] = 'Logger';
		return Model::getInstance($args);
	}
		
	public function __construct($args)
	{
		global $LOG_SYSLOG, $LOG_SYSLOG_FACILITY, $LOG_BACKTRACE, $LOG_STDERR;
		
		if(isset($args['db']))
		{
			parent::__construct($args);
		}
		if(!self::$pid) self::$pid = getmypid();
		if(!self::$ferr) self::$ferr = fopen('php://stderr', 'w');
		if(defined('CLUSTER_IRI') && !self::$clusterName)
		{
			require_once(APPS_ROOT . 'cluster/model.php');
			$cluster = ClusterModel::getInstance();
			self::$clusterName = $cluster->clusterName;
			self::$instanceName = $cluster->instanceName;
			self::$clusterSpec = ' ' . self::$clusterName . '/' . self::$instanceName;
		}
		if(!isset(self::$syslog)) self::$syslog = $LOG_SYSLOG;
		if(!isset(self::$syslogFacility)) self::$syslogFacility = $LOG_SYSLOG_FACILITY;
		if(!isset(self::$backtrace)) self::$backtrace = $LOG_BACKTRACE;
		if(!isset(self::$stderr)) self::$stderr = $LOG_STDERR;
		if($this->db)
		{
			do
			{
				$this->db->begin();
				if(self::$clusterName)
				{
					if($this->db->row('SELECT * FROM {log_count} WHERE "log_cluster" = ? AND "log_instance" = ?', self::$clusterName, self::$instanceName))
					{
						$this->db->rollback();
						break;
					}
				}
				else
				{
					if($this->db->row('SELECT * FROM {log_count} WHERE "log_cluster" IS NULL AND "log_instance" IS NULL'))
					{
						$this->db->rollback();
						break;
					}			
				}
				$this->db->insert('log_count', array(
					'log_cluster' => self::$clusterName,
					'log_instance' => self::$instanceName,
					'log_count' => 0,
				));
			}
			while(!$this->db->commit());
		}
	}
	
	public function logEvent($message, $priority, $ident, $userScheme, $userUuid)
	{
		$file = null;
		$line = null;
		
		if(in_array($priority, self::$backtrace))
		{
			$bt = @debug_backtrace(false);
			if(is_array($bt) && isset($bt[1]))
			{
				if(isset($bt[1]['file'])) $file = $bt[1]['file'];
				if(isset($bt[1]['line'])) $line = $bt[1]['line'];
			}
		}
		$msg = null;
		if($ident)
		{
			$msg .= $ident;
		}
		$msg .= '[' . self::$pid . ']: ';
		if(in_array($priority, self::$syslog))
		{
			openlog($ident, LOG_ODELAY|LOG_PID, self::$syslogFacility);		
			syslog($priority, $message);
		}
		if(self::$stderr === true || (is_array(self::$stderr) && in_array($priority, self::$stderr)))
		{
			fwrite(self::$ferr, '[' . strftime('%Y-%m-%d %H:%M:%S %z') . self::$clusterSpec . '] ' .  $msg . $message . "\n");	
		}
		if($this->db)
		{
			$priority = (isset(self::$priorities[$priority]) ? self::$priorities[$priority] : 'INFO');		
			$this->db->insert('log_entry', array(
				'@entry_timestamp' => $this->db->now(),
				'entry_cluster' => self::$clusterName,
				'entry_instance' => self::$instanceName,
				'entry_scheme' => $userScheme,
				'entry_uuid' => $userUuid,
				'entry_pid' => self::$pid,
				'entry_priority' => $priority,
				'entry_facility' => $ident,
				'entry_file' => $file,
				'entry_line' => $line,
				'entry_message' => $message,
			));
			if(self::$clusterName)
			{
				$this->db->exec('UPDATE {log_count} SET "log_count" = "log_count" + 1 WHERE "log_cluster" = ? AND "log_instance" = ?', self::$clusterName, self::$instanceName);
			}
			else
			{
				$this->db->exec('UPDATE {log_count} SET "log_count" = "log_count" + 1 WHERE "log_cluster" IS NULL AND "log_instance" IS NULL', self::$clusterName, self::$instanceName);		
			}
		}
	}
}