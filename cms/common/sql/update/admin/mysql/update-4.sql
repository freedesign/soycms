CREATE TABLE LoginErrorLog (
	id INTEGER PRIMARY KEY AUTO_INCREMENT,
	ip VARCHAR(128) UNIQUE NOT NULL,
	count INTEGER NOT NULL DEFAULT 0,
	successed INTEGER NOT NULL DEFAULT 0,
	start_date INTEGER NOT NULL,
	update_date INTEGER NOT NULL
)ENGINE = InnoDB;