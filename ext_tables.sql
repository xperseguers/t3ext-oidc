#
# Table structure for table 'tx_oidc_application'
#
CREATE TABLE tx_oidc_application (
	uid int(11) unsigned NOT NULL auto_increment,
	pid int(11) unsigned DEFAULT '0' NOT NULL,

	tstamp int(11) unsigned DEFAULT '0' NOT NULL,
	crdate int(11) unsigned DEFAULT '0' NOT NULL,
	cruser_id int(11) unsigned DEFAULT '0' NOT NULL,
	deleted tinyint(1) unsigned DEFAULT '0' NOT NULL,
	hidden tinyint(1) unsigned DEFAULT '0' NOT NULL,

	name varchar(255) DEFAULT '' NOT NULL,
	oauth_client_key varchar(255) DEFAULT '' NOT NULL,
	oauth_client_secret varchar(255) DEFAULT '' NOT NULL,
	endpoint_authorize varchar(255) DEFAULT '' NOT NULL,
	endpoint_token varchar(255) DEFAULT '' NOT NULL,
	endpoint_revoke varchar(255) DEFAULT '' NOT NULL,
	endpoint_userinfo varchar(255) DEFAULT '' NOT NULL,
	endpoint_checksession varchar(255) DEFAULT '' NOT NULL,
	endpoint_logout varchar(255) DEFAULT '' NOT NULL,
	domains text NOT NULL,
	access_token text NOT NULL,
	state varchar(255) DEFAULT '' NOT NULL,

	PRIMARY KEY (uid),
	KEY parent (pid)
) ENGINE=InnoDB;
