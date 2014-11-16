<?php

/**
 * Walkway
 * =======
 *
 * A modular router for PHP.
 *
 * @author Rasmus Schultz <http://blog.mindplay.dk>
 * @license GPL3 <http://www.gnu.org/licenses/gpl-3.0.txt>
 */

namespace mindplay\walkway;

/**
 * This Exception is thrown if there is a problem invoking a function.
 * @see Route::invoke()
 */
class InvocationException extends FunctionException
{
}
