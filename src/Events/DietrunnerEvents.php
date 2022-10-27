<?php
/**
 *
 */

namespace Sichikawa\Dietrunner\Events;

final class DietrunnerEvents
{
    const BOOT = 'dietcube.boot';

    const ROUTING = 'dietcube.routing';

    const EXECUTE_ACTION = 'dietcube.execute_action';

    const FILTER_RESPONSE = 'dietcube.filter_response';

    const FINISH_REQUEST = 'dietcube.finish_request';
}
