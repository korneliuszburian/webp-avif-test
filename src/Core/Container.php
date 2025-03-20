<?php

namespace WpImageOptimizer\Core;

class Container {
    private array $services = [];
    private array $instances = [];
    
    /**
     * Register a service with the container
     */
    public function set(string $id, callable $factory): void {
        $this->services[$id] = $factory;
        unset($this->instances[$id]);
    }
    
    /**
     * Get a service from the container
     */
    public function get(string $id) {
        if (!isset($this->instances[$id])) {
            if (!isset($this->services[$id])) {
                throw new \InvalidArgumentException("Service not found: $id");
            }
            $this->instances[$id] = ($this->services[$id])($this);
        }
        return $this->instances[$id];
    }
    
    /**
     * Check if a service exists in the container
     */
    public function has(string $id): bool {
        return isset($this->services[$id]);
    }
}
