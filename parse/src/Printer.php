<?php

namespace Parse;

class Printer
{
    private $output;

    public function out($message)
    {
        $this->output .= $message . "\n";
    }

	public function outTable($fields, $data)
    {
        // trim all cells
        $data = array_map(function($row){
            return array_map('trim', $row);
        }, $data);

        array_unshift($fields, '#');
        $numberRow = 1;
        $data = array_map(
            function ($row) use (&$numberRow) {
                array_unshift($row, $numberRow++);
                return $row;
            }, $data
        );
        $sizes = [];
        $header = '';
        foreach ($fields as $i => $fieldName) {
            $column = array_column($data, $i);
            $columnWidths = array_map('strlen', $column);
            $columnWidths[] = strlen($fieldName);
            $maxWidth = max($columnWidths);
            $sizes[$i] = $maxWidth;
            $header .= str_pad($fieldName, $maxWidth) . ' | ';
        }
        $out = str_repeat("-", strlen($header));
        $this->out($header);
        $this->out(str_repeat("-", strlen($header)));
        foreach ($data as $row) {
            $outRow = '';
            foreach (array_values($row) as $i => $cell) {
                $outRow .= str_pad($cell, $sizes[$i]) . ' | ';
            }
            $this->out($outRow);
        }
        return $this->output;
    }
}