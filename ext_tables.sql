#
# Table structure for table 'fe_users'
#
CREATE TABLE fe_users (
	tx_oidc int(11) unsigned DEFAULT '0' NOT NULL,

	KEY fk_oidc (tx_oidc)
);
