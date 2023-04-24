CREATE TABLE fe_users
(
	tx_oidc varchar(100) DEFAULT '' NOT NULL,

	KEY fk_oidc (tx_oidc)
);

CREATE TABLE fe_groups
(
	tx_oidc_pattern varchar(255) DEFAULT '' NOT NULL
);
