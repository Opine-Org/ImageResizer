<?php
/**
 * Opine\ImageResizer\Controller
 *
 * Copyright (c)2013, 2014 Ryan Mahoney, https://github.com/Opine-Org <ryan@virtuecenter.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
namespace Opine\ImageResizer;

class Controller {
    private $service;
    private $secret;

    public function __construct ($service, $secret) {
        $this->service = $service;
        $this->secret = $secret;
    }

    public function securityFilter () {
        if (!isset($_SERVER['QUERY_STRING']) || empty($_SERVER['QUERY_STRING'])) {
            http_response_code(404);
            return false;
        }
        $pieces = func_get_args();
        if (count($pieces) < 6) {
            http_response_code(404);
            return false;
        }
        $input  = implode('/', $pieces);
        $message = $this->secret->decrypt($_SERVER['QUERY_STRING']);
        if ($input != $message) {
            return false;
        }
        return true;
    }

    public function resizeImage () {
        $pieces = func_get_args();
        $width = array_shift($pieces);
        if (!is_numeric($width)) {
            $this->service->error('Invalid Width: ' . $width);
        }
        $height = array_shift($pieces);
        if (!is_numeric($height)) {
            $this->service->error('Invalid Height ' . $height);
        }
        $cropratio = array_shift($pieces);
        if (substr_count($cropratio, ':') != 1) {
            $this->service->error('Invalid Crop Ratio');
        }
        $cropPieces = explode(':', $cropratio, 2);
        if ((!isset($cropPieces[0]) || !is_numeric($cropPieces[0])) || (!isset($cropPieces[1]) || !is_numeric($cropPieces[1])) ) {
            $this->service->error('Invalid Crop Ratio');
        }
        $type = array_shift($pieces);
        if (!in_array($type, ['L', 'E','ES'])) {
            $this->service->error('Invalid Conversion Type: ' . $type);
        }
        $file = implode('/', $pieces);
        $extension = pathinfo($file, \PATHINFO_EXTENSION);
        if (!in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif'])) {
            $this->service->error('Invalid Image Type: ' . $extension);
        }
        $this->service->preProcess($file, $width, $height, $cropratio, $type, $extension);
    }
}