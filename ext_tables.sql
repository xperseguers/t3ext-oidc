#
# Table structure for table 'fe_users'
#
CREATE TABLE fe_users (
	tx_oidc int(11) unsigned DEFAULT '0' NOT NULL,

	KEY fk_oidc (tx_oidc)
);

#
# Table structure for table 'fe_groups'
#
CREATE TABLE fe_groups (
	tx_oidc_pattern varchar(50) DEFAULT '' NOT NULL
);
