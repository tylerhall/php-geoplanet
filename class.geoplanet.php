<?PHP
    // Yahoo! GeoPlanet PHP API Implementation
    // Based on API Documentation here: http://developer.yahoo.com/geo/guide/index.html
    // Tyler Hall <tylerhall@gmail.com>
    // Code released under the MIT License.

    class GeoPlanetException extends Exception
    {
        const BAD_XML        = '1';
        const BAD_REQUEST    = '2';
        const NOT_FOUND      = '3';
        const RESOURCE_DNE   = '4';
        const NOT_ACCEPTABLE = '5';

        public $response;

        public function __construct($msg, $code, $response = null)
        {
            parent::__construct($msg, $code);
            $this->response = $response;
        }
    }

    class GeoPlanet
    {
        const API_URI = 'http://where.yahooapis.com/v1/';
        const LANG    = 'en-US';

        public $appID;

        public function __construct($app_id)
        {
            $this->appID = $app_id;
        }

        // places
        // Returns a collection of places that match a specified place name, and optionally, a specified place type.
        // http://developer.yahoo.com/geo/guide/api-places.html
        // TODO: Notify of missing count param in Example 9
        public function getPlaces($q, $type = null, $count = 0)
        {
            $q = urlencode($q);
            if(is_null($type))
                $query = ".q($q)";
            else
            {
                $type = urlencode($type);
                $query = '$and(' . ".q($q)" . ",.type($type))";
            }

            $query .= ";count=$count";

            $xml = $this->sendRequest('places' . $query);

            $results = array();
            foreach($xml->place as $place)
                $results[] = $this->parsePlace($place);
            return $results;
        }

        // place/{woeid}
        // Returns a resource containing the long representation of a place.
        // http://developer.yahoo.com/geo/guide/api-place.html
        public function getPlace($woeid)
        {
            $xml = $this->sendRequest("place/$woeid");
            return $this->parsePlace($xml);
        }

        // place/{woeid}/parent
        // Returns a resource for the parent of a place.
        // http://developer.yahoo.com/geo/guide/api-parent.html
        public function getParent($woeid)
        {
            $xml = $this->sendRequest("place/$woeid/parent");
            return $this->parsePlace($xml);
        }

        // place/{woeid}/ancestors
        // Returns a collection of places in the parent hierarchy
        // http://developer.yahoo.com/geo/guide/api-ancestors.html
        public function getAncestors($woeid, $count = 0)
        {
            $xml = $this->sendRequest("place/$woeid/ancestors;count=$count");

            $results = array();
            foreach($xml->place as $place)
                $results[] = $this->parsePlace($place);
            return $results;
        }

        // place/{woeid}/belongtos
        // Returns a collection of places that have a place as a child or descendant (child of a child, etc).
        // http://developer.yahoo.com/geo/guide/api-belongtos.html
        public function getBelongtos($woeid, $type = null, $count = 0)
        {
            if(!is_null($type))
            {
                $type = urlencode($type);
                $type = ".type($type)";
            }
            else
                $type = '';

            $xml = $this->sendRequest("place/$woeid/belongtos$type;count=$count");

            $results = array();
            foreach($xml->place as $place)
                $results[] = $this->parsePlace($place);
            return $results;
        }

        // place/{woeid}/neighbors
        // Returns a collection of places that neighbor of a place.
        // http://developer.yahoo.com/geo/guide/api-neighbors.html
        public function getNeighbors($woeid, $count = 0)
        {
            $xml = $this->sendRequest("place/$woeid/neighbors;count=$count");

            $results = array();
            foreach($xml->place as $place)
                $results[] = $this->parsePlace($place);
            return $results;
        }

        // place/{woeid}/siblings
        // Returns a collection of places that are siblings of a place.
        // http://developer.yahoo.com/geo/guide/api-siblings.html
        public function getSiblings($woeid, $count = 0)
        {
            $xml = $this->sendRequest("place/$woeid/siblings;count=$count");

            $results = array();
            foreach($xml->place as $place)
                $results[] = $this->parsePlace($place);
            return $results;
        }

        // place/{woeid}/children
        // Returns a collection of places that are children of a place.
        // http://developer.yahoo.com/geo/guide/api-children.html
        public function getChildren($woeid, $type = null, $count = 0)
        {
            if(!is_null($type))
            {
                $type = urlencode($type);
                $type = ".type($type)";
            }
            else
                $type = '';

            $xml = $this->sendRequest("place/$woeid/children$type;count=$count");

            $results = array();
            foreach($xml->place as $place)
                $results[] = $this->parsePlace($place);
            return $results;
        }

        // placetypes
        // Returns the complete collection of place types supported in GeoPlanet.
        // http://developer.yahoo.com/geo/guide/api-placetypes.html
        public function getPlaceTypes()
        {
            $xml = $this->sendRequest('placetypes');
            $place_types = array();
            foreach($xml->placeType as $type)
                $place_types[(int) $type->placeTypeName['code']] = (string) $type->placeTypeName;
            return $place_types;
        }

        // placetype
        // Returns a resource that describes a single place type.
        // http://developer.yahoo.com/geo/guide/api-placetype.html
        public function getPlaceType($place_code)
        {
            $xml = $this->sendRequest('placetype/' . $place_code);
            return (string) $xml->placeTypeName;
        }

        private function parsePlace($place)
        {
            $p = array();
            $p['woeid'] = (string) $place->woeid;
            $p['placeTypeName'] = (string) $place->placeTypeName;
            $p['name'] = (string) $place->name;
            $p['country'] = (string) $place->country;
            $p['admin1'] = (string) $place->admin1;
            $p['admin2'] = (string) $place->admin2;
            $p['admin3'] = (string) $place->admin3;
            $p['locality1'] = (string) $place->locality1;
            $p['locality2'] = (string) $place->locality2;
            $p['postal'] = (string) $place->postal;
            $p['centroid'] = array('lat' => (string) $place->centroid->latitude, 'lng' => (string) $place->centroid->longitude);
            $p['boundingBox'] = array('southWest' => array('lat' => (string) $place->boundingBox->southWest->latitude, 'lng' => (string) $place->boundingBox->southWest->longitude),
                                      'northEast' => array('lat' => (string) $place->boundingBox->northEast->latitude, 'lng' => (string) $place->boundingBox->northEast->longitude));
            return $p;
        }

        private function sendRequest($query, $select = 'long')
        {
            $url = sprintf('%s%s?select=%s&appid=%s',
                           self::API_URI,
                           $query,
                           $select,
                           $this->appID);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            // curl_setopt($ch, CURLOPT_VERBOSE, 1);

            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/xml', 'Accept-Language: ' . self::LANG));

            $xmlstr = curl_exec($ch);

            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if($http_code == '400') throw new GeoPlanetException('A valid appid parameter is required for this resource', GeoPlanetException::BAD_REQUEST, $xmlstr);
            if($http_code == '404') throw new GeoPlanetException('URI has no match in the display map', GeoPlanetException::NOT_FOUND, $xmlstr);
            if($http_code == '404') throw new GeoPlanetException('Could not find the resource xxx', GeoPlanetException::RESOURCE_DNE, $xmlstr); // Yeah, hmmm...
            if($http_code == '406') throw new GeoPlanetException('Requested representation not available for this resource', GeoPlanetException::NOT_ACCEPTABLE, $xmlstr);

            curl_close($ch);

            $xml = simplexml_load_string($xmlstr);
            if($xml === false) throw new GeoPlanetException('Could not parse XML', GeoPlanetException::BAD_XML, $xmlstr);

            return $xml;
        }
    }
