<?php
class Postforge_Loader {

    protected $actions = array();
    protected $filters = array();

    public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
        $this->actions[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
    }

    public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
        $this->filters[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
    }

    public function run() {
        foreach ( $this->actions as $hook_data ) {
            add_action(
                $hook_data['hook'],
                array( $hook_data['component'], $hook_data['callback'] ),
                $hook_data['priority'],
                $hook_data['accepted_args']
            );
        }

        foreach ( $this->filters as $hook_data ) {
            add_filter(
                $hook_data['hook'],
                array( $hook_data['component'], $hook_data['callback'] ),
                $hook_data['priority'],
                $hook_data['accepted_args']
            );
        }
    }
}
