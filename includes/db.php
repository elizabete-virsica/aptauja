<?php
// Datubāzes savienojums (SQLite fails data mapē)

session_start();

$pdo = new PDO('sqlite:' . __DIR__ . '/../data/aptauja.db');

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("PRAGMA foreign_keys=ON");

$pdo->exec("
  CREATE TABLE IF NOT EXISTS users(
  id INTEGER PRIMARY KEY, 
  username TEXT UNIQUE, 
  password TEXT
  );

  CREATE TABLE IF NOT EXISTS surveys(
  id INTEGER PRIMARY KEY, 
  user_id INTEGER, 
  title TEXT, 
  expires TEXT, 
  collect_email 
  INTEGER DEFAULT 0
  );

  CREATE TABLE IF NOT EXISTS questions(
  id INTEGER PRIMARY KEY, 
  survey_id INTEGER, 
  text TEXT, 
  type TEXT, 
  options TEXT,
  FOREIGN KEY(survey_id) 
  REFERENCES surveys(id) ON DELETE CASCADE
  );
  CREATE TABLE IF NOT EXISTS responses(
  id INTEGER PRIMARY KEY, 
  survey_id INTEGER, 
  email TEXT, 
  created TEXT,
  FOREIGN KEY(survey_id) 
  REFERENCES surveys(id) ON DELETE CASCADE
  );

  CREATE TABLE IF NOT EXISTS answers(
  id INTEGER PRIMARY KEY, 
  response_id INTEGER, 
  question_id INTEGER, 
  value TEXT,
  FOREIGN KEY(response_id) 
  REFERENCES responses(id) ON DELETE CASCADE
  );
  
");
