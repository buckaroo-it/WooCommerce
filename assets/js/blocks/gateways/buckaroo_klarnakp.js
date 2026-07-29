import React from 'react';

// Gender selection removed from checkout to reduce friction.
// The processor always sends "Unknown" for the mandatory Klarna gender parameter.
function KlarnaKp() {
    return <div id="buckaroo_klarnapay" />;
}

export default KlarnaKp;
