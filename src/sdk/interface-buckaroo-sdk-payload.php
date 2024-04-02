<?php


interface Buckaroo_Sdk_Payload_Interface
{
    /**
     * Get request action
     *
     * @return string
     */
    public function get_action(): string;

    /**
     * Get request body
     *
     * @return array
     */
    public function get_body(): array;

    /**
     * Get request mode: test|live
     *
     * @return string
     */
    public function request_mode(): string;

    /**
     * Get payment code required for sdk
     *
     * @return string
     */
    public function get_sdk_code(): string;
}
