<?php
# Parsedown (https://parsedown.org)
# Placeholder for markdown parser file
class Parsedown {
    public function text($text) {
        return nl2br(htmlentities($text));
    }
}
?>