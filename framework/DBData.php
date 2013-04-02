// The author of this code is Hwi Jun KIM a.k.a pcaffeine

<?php

require_once("framework/MetaClass.php");

class DBData extends MetaClass {

  protected $tableName;

  protected $belongTo;
  protected $belongWith;

  protected $slave;
  protected $master;

  // tableName => array()
  protected $colNames;
  protected $propNames;

  protected $defaultFilter;
  protected $filter;

  // protected $container;
  
  public function __consruct($tableName) {
    $theWorld = TheWorld::instance();
    $this->slave = $theWorld->slave;
    $this->master = $theWorld->master;

    $this->setPropNames();

    $this->defaultFilter = null;
    $this->filter = null;
    $this->tableName = $tableName;

    $this->belongTo = null;
    $this->belongWith = null;

    $this->container = array();
  }



  public function fetchOne($where, $orderBy, $limit, $context) {
    return $this->fetch($where, $orderBy, 1);
  }

  public function fetch($where = null, $orderBy = null, $limit = null, $context = null) {
    $sql = "SELECT ";
    $i = 1;
    $n = count($colNames);

    if ($this->belongTo != null) {
      $sql = $sql . $this->makeColNames($this->tableName);
      $sql = $sql . "," . $this->makeColNames($this->belongTo);
    }

    if ($where !== null) {
      $sql = $sql . " WHERE ";

      $i = 1;
      $n = count($where);
      foreach($where as $col => $cond) {
        $sql = $sql . $col . " = " . $this->slave->quote($cond);

        if ($i != $n) {
          $sql = $sql . " AND ";
        }

        ++$i;
      }
    }

    // join
    if ($this->belongTo !== null) {
      if ($where === null) {
        $sql = $sql . " WHERE ";
      }
      $sql = $sql . $tableName . "." . $this->belongWith . " = " . $this->belongTo . "id";
    }


     
    if ($orderBy != null) {
      $sql = $sql . " ORDER BY " . $orderBy;
    }

    if ($limit != null) {
      $sql = $sql . " LIMIT " . $limit;
    }

    $result = $this->slave->query($sql);

    $result = array();
    foreach($result as $row) {
      foreach($rows as $propName => $val) {
        if ($context === null) {
          // only for fetchOne
          $context->$propName = $val;
          $result = array($context);
        }
        else {
          $object = new DBData();
          $object->$propName = $val;
          $result[] = $object;
        }
      }
    }

    return $result;
  }

  public function getCount() {
    $sql = "SELECT count(id) as cnt FROM " . $this->tableName . " LIMIT 1";
    $rows = $this->slave->query($sql);
    $row = $rows[0];

    return $row["cnt"];
  }

  /*
  public function __get($propName) {
    if (!array_exists($propName, $this->container)) {
      return false;
    }

    return $this->container[$propName];
  }

  public function __set($propName, $val) {
    $this->container[$propName] = $val;

    return $this;
  }
  */

  public function load() {
    if ($this->id === false) {
      throw new Exception("DBData::load(): id is not specified.");
    }
    
    $where = array("id" => #this->id);
    $this->fetch($where, null, null, $this);

    return $this;
  }

  public function autoSetColNames() {
    $sql = "DESCRIBE " . $this->tableName;
    $rows = $this->slave->query($sql);
    foreach($rows as $row) {
      $field = $row["Field"];
      $this->colNames[] = $field;
      if ($this->belongTo != null) {
        $modifiedField = "pri_" . $field;
        $this->propNames[$this->tableName][$field] = $modifiedField;
      }
      else {
        $this->propNames[$this->tableName][$field] = $field;
      }
    }

    if ($this->belongTo == null) {
      return $this;
    }

    $sql = "DESCRIBE " . $this->belongTo;
    $rows = $this->slave->query($sql);
    foreach($rows as $row) {
      $field = $row["Field"];

      $modifiedField = "sec_" . $field;
      $this->colNames[] = $field;
      $this->secPropNames[$this->tableName][$field] = $modifiedField;
    }

    return $this;
  }

  public function save() {
    if (array_key_exists("id", $this->container)) {
      $this->saveUpdate();
    }
    else {
      $this->saveNew();
    }
    
    return $this;
  }

  protected function saveUpdate() {
    if ($this->belongTo !== null) {
      throw new Exception("DBData::saveUpdate(): saveUpdate() cannot be invoked when there is join.");
    }

    // update tablename set foo = bar where id = ?
    $sql = "UPDATE " . $this->tableName . " SET ";
    $i = 1;
    $n = count($this->propNames);
    foreach($this->propNames as $propName) {
      $val = $this->propName;
      $sql = $sql . $propName . " = " . $this->$propName . " ";
      
      if ($i != $n) {
        $sql = $sql . ",";
      }

      ++$i
    }

    $sql = $sql . " WHERE id = " . $this->id;

    $this->master->query($sql);
  }

  // only invoked when there is no join.
  protected function saveNew() {
    if ($this->belongTo !== null) {
      throw new Exception("DBData::saveNew(): saveNew() cannot be invoked when there is join.");
    }

    $sql = "INSERT INTO " . $this->tableName . " (";
    $i = 1;
    $n = count($this->propNames);
    foreach($this->propNames as $colName) {
      $sql = $sql . $colName;
      if ($i != $n) {
        $sql = $sql . ",";
      }

      ++$i;
    }

    $sql = $sql . ") VALUES(";
    $i = 1;
    $n = count($propNames);
    foreach($this->propNames as $propName) {
      $val = $this->$propName;
      
      if ($i != $n) {
        $sql = $sql . ",";
      }

      ++$i;
    }
    $sql = $sql . ")";
    
    $this->master->query($sql);
  }

  protected function makeColNames($tableName, $suffix) {
    $sql = "";
    $colNames = $this->colNames[$tableName];
    foreach($colNames as $colName => $modifiedColName) {
      $sql = $sql . $tableName . "." . $colName . sprintf(" as %s ", $modifiedField);
      
      if ($i != $n) {
        $sql = $sql . ",";
      }
      
      ++$i;
    }

    return $sql;
  }

}

?>