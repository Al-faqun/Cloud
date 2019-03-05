<?php

namespace App\Filesystem;


class FileEntity
{
    private $name;
    private $id;
    
    
    public function __construct($id, $name)
    {
        $this->setId($id);
        $this->setName($name);
    }
    
    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }
    
    /**
     * @param mixed $id
     */
    public function setId($id): void
    {
        $this->id = $id;
    }
    
    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }
    
    /**
     * @param mixed $name
     */
    public function setName($name): void
    {
        $this->name = $name;
    }
}