<?php

namespace Pendolf\Mandrill\Exceptions;

/**
 * A dedicated IP cannot be provisioned while another request is pending.
 */
class IpProvisionLimit extends Error
{
}