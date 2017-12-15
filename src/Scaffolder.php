<?php

namespace GKA\Noctis\Service\Propel\Scaffolder;


/*
common usage from a Noctis Controller

Scaffolder::create('UnitName')->wildCardFetch($this->response);
*/

class Scaffolder extends \IObject{

  static function create($modelName){
		$class = get_called_class();
		//var_dump();

		$o = new $class($modelName);
		return $o->chain_constructor();
	}

  function __construct($modelName){
    list($this->class,$this->classQuery,$this->mapInstance,$this->modelName) = $this->propelClasses($modelName);
  }

  function fetch(){
    $load_fk = boolval(Env::getRequest()->request->get('fk'));
    $fk_depth = Env::getRequest()->request->get('fk_depth');
    if(!$fk_depth){
      $fk_depth = 1;
    }
    if(empty($model)){
      $model =  $this->getUnitName(); //name of inherited class without namespace

    }
    //list($class,$classQuery,$mapInstance,$model)=$this->propelClasses($model);


    if($id =Env::getRequest()->request->get('key_id')){
      //we have an id it's an update
      $query = $this->classQuery::create();
    //  $query->setFormatter('Propel\Runtime\Formatter\ArrayFormatter');
      $query->filterById($id);
      if($load_fk){
        $query = $this->fkQueryGenerator($this->mapInstance,$fk_depth,0,$query);
      }

      if(!($object = $query->findOne())){
        $this->setError('Object Not Found',404);
        return false;
      }
    }
    return $object;
  }

  function wildCardFetch($response){

    $setFunction = "set".$model;
    if($object = $this->fetch()){

      $response->$setFunction($object->toArray(\Propel\Runtime\Map\TableMap::TYPE_PHPNAME,true,array(),true));
    }
    return $response;
  }

  function propelClasses($model){
    $class = "\app\model\\".ucfirst($model);
    $classQuery =$class."Query";
    $mapClass = "\app\model\Map\\".ucFirst($model)."TableMap";

    $mapInstance = $mapClass::getTableMap();
    return array($class,$classQuery,$mapInstance,$model);
  }

  function wildCardFetchAll($response){

    $load_fk = boolval(Env::getRequest()->request->get('fk'));
    $fk_depth = Env::getRequest()->request->get('fk_depth');

    list($class,$classQuery,$mapInstance,$model)=$this->propelClasses($model);
    $setFunction = "set".$model."s";

    $query = $classQuery::create();
    $query->setFormatter('Propel\Runtime\Formatter\ArrayFormatter');

    if($load_fk){
      $query = $this->fkQueryGenerator($this->mapInstance,$fk_depth,0,$query);
    }
    $response->$setFunction($this->cleanupData($query->find()->toArray()));
    return $response;
  }

  // generate a query with foreign key by level
  function fkQueryGenerator($mapInstance,$depth,$current_depth,$query){
    $continue = $depth > $current_depth;
    $rels = $mapInstance->getRelations();
//    var_dump('rel');
    foreach($rels as $relName => $rel){
      //we only want many to one rela tion ship
  //    var_dump($rel->getType()); var_dump($rel->getName());
      if(\Propel\Runtime\Map\RelationMap::MANY_TO_ONE == $rel->getType()){

        $leftJoin = "leftJoinWith".$rel->getName();
        $query->$leftJoin();
        if($continue){
          $f = 'use'.$rel->getName()."Query";
          $query = $query->$f();
          $query = $this->fkQueryGenerator($rel->getForeignTable(),$depth,$current_depth+1,$query);
          $query = $query->endUse();
        }
      }
    }

    return $query;
  }
}
