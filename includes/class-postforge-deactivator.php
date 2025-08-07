<?php
class Postforge_Deactivator {
    public static function deactivate() {
        flush_rewrite_rules();
    }
}
