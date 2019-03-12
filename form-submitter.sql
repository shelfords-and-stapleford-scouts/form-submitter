CREATE TABLE submission (
  submission_id int(10) unsigned not null auto_increment primary key,
  code          varchar(64)      not null default '',   unique (code),
  completed     enum('yes','no') not null default 'no',
  userdata      json             not null
);
