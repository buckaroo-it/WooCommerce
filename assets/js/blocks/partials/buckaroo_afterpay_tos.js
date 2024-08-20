import React from 'react';
import { __ } from '@wordpress/i18n';

function AfterPayTos({ field }) {
  return (
    <div>
      <a href="#" target="_blank">{__('Accept Riverty conditions:', 'wc-buckaroo-bpe-gateway')}</a>
      <span className="required">*</span>
      <input
        id={`${field}-accept`}
        name={`${field}-accept`}
        type="checkbox"
        value="ON"
      />
      <div className="required" style={{ float: 'right' }}>
        *
        {__('Required', 'wc-buckaroo-bpe-gateway')}
      </div>
    </div>
  );
}

export default AfterPayTos;
