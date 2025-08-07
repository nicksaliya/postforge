<?php
class Postforge_Activator {
    public static function activate() {
        // For now, flush rewrite rules
        flush_rewrite_rules();
    }
}
