/**
 * Import dependencies.
 */
const { __ } = wp.i18n;
import { Button, __experimentalInputControl as InputControl  } from '@wordpress/components';
import {EDLFW_ERRORS} from "../errors";

/**
 * Show simple API form.
 *
 * @param errors
 * @param api_key
 * @param setApiKey
 * @param setEnabled
 * @returns {JSX.Element}
 * @constructor
 */
export const EDLFW_SIMPLE_API_FORM = ( { errors, apiKey, setApiKey, setEnabled, url, setUrl } ) => {
  /**
   * Handle the login itself.
   */
  function do_login() {
    // bail if one setting is not given.
    if( ! apiKey ) {
      return;
    }

    // enable the listing.
    setEnabled( true )
  }

  return (
    <>
      <h2>{__( 'Enter your API key' )}</h2>
      {errors && <EDLFW_ERRORS errors={errors}/>}
      <InputControl label={__( 'ID' )} value={url} onChange={(value) => setUrl( value )}/>
      <InputControl label={__( 'Key' )} value={apiKey} onChange={(value) => setApiKey( value )}/>
      <Button variant="primary" onClick={() => do_login()}>Show directory</Button>
    </>
  )
}
