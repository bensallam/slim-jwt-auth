<?php

/*
 * This file is part of Slim JSON Web Token Authentication middleware
 *
 * Copyright (c) 2015 Mika Tuupola
 *
 * Licensed under the MIT license:
 *   http://www.opensource.org/licenses/mit-license.php
 *
 * Project home:
 *   https://github.com/tuupola/slim-jwt-auth
 *
 */

namespace Slim\Middleware;

class JwtAuthentication extends \Slim\Middleware
{
    private $options = array(
        "path" => null,
        "environment" => "HTTP_AUTHORIZATION"
    );

    /**
     * Create a new JwtAuthentication Instance
     */
    public function __construct($options = array())
    {
        /* Setup stack for rules */
        $this->rules = new \SplStack;

        /* Store passed in options overwriting any defaults. */
        $this->hydrate($options);
    }

    /**
     * Call the middleware
     */
    public function call()
    {
        /* If rules say we should not authenticate call next and return. */
        if (false === $this->shouldAuthenticate()) {
            $this->next->call();
            return;
        }

        /* If token cannot be found return with 401 Unauthorized. */
        if (false === $token = $this->fetchToken()) {
            $this->app->response->status(401);
            return;
        }

        /* If token cannot be decoded return with 400 Bad Request. */
        if (false === $decoded = $this->decodeToken($token)) {
            $this->app->response->status(400);
            return;
        }

        /* Here do something with the decoded data. */

        /* Everything ok, call next middleware. */
        $this->next->call();
    }

    /**
     * Check if middleware should authenticate
     *
     * @return boolean True if middleware should authenticate.
     */
    public function shouldAuthenticate()
    {
        /* If any of the rules in stack return false will not authenticate */
        foreach ($this->rules as $callable) {
            if (false === $callable($this->app)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if middleware should not authenticate
     *
     * @return boolean True if middleware should not authenticate.
     */
    public function shouldNotAuthenticate()
    {
        return ! $this->shouldAuthenticate();
    }

    /**
     * Fetch the access token
     *
     * @return string|null Base64 encoded JSON Web Token or null if not found.
     */
    public function fetchToken()
    {
        /* If using PHP in CGI mode and non standard environment */
        if (isset($_SERVER[$this->options["environment"]])) {
            $header = $_SERVER[$this->options["environment"]];
        } else {
            $header = $this->app->request->headers("Authorization");
        }
        if (preg_match("/Bearer\s+(.*)$/i", $header, $matches)) {
            return $matches[1];
        }
        return false;
    }

    public function decodeToken($token)
    {
        try {
            return \JWT::decode(
                $token,
                $this->options["secret"],
                array("HS256", "HS512", "HS384", "RS256")
            );
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * Hydate options from given array
     *
     * @param array $data Array of options.
     * @return self
     */
    private function hydrate($data = array())
    {
        foreach ($data as $key => $value) {
            $method = "set" . ucfirst($key);
            if (method_exists($this, $method)) {
                call_user_func(array($this, $method), $value);
            }
        }
        return $this;
    }

    /**
     * Get the environment name where to search the token from
     *
     * @return string Name of environment variable.
     */
    public function getEnvironment()
    {
        return $this->options["environment"];
    }

    /**
     * Set the environment name where to search the token from
     *
     * @return self
     */
    public function setEnvironment($environment)
    {
        $this->options["environment"] = $environment;
        return $this;
    }

    /**
     * Get the secret key
     *
     * @return string
     */
    public function getSecret()
    {
        return $this->options["secret"];
    }

    /**
     * Set the secret key
     *
     * @return self
     */
    public function setSecret($secret)
    {
        $this->options["secret"] = $secret;
        return $this;
    }

    /**
     * Get the rules stack
     *
     * @return \SplStack
     */
    public function getRules()
    {
        return $this->rules;
    }

    /**
     * Set all rules in the stack
     *
     * @return self
     */
    public function setRules(array $rules)
    {
        /* Clear the stack */
        unset($this->rules);
        $this->rules = new \SplStack;
        /* Add the rules */
        foreach ($rules as $callable) {
            $this->addRule($callable);
        }
        return $this;
    }

    /**
     * Add rule to the stack
     *
     * @param callable $callable Callable which returns a boolean.
     * @return self
     */
    public function addRule($callable)
    {
        $this->rules->push($callable);
        return $this;
    }
}