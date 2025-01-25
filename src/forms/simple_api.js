/**
 * Import dependencies.
 */
const { __ } = wp.i18n;
import {
  Button,
  ToggleControl,
  __experimentalInputControl as InputControl
} from '@wordpress/components';
import {EDLFW_ERRORS} from "../errors";

/**
 * Show simple API form.
 *
 * @param errors
 * @param api_key
 * @param setApiKey
 * @param setEnabled
 * @param saveCredentials
 * @param setSaveCredentials
 * @returns {JSX.Element}
 * @constructor
 */
export const EDLFW_SIMPLE_API_FORM = ( { errors, apiKey, setApiKey, setEnabled, url, setUrl, saveCredentials, setSaveCredentials } ) => {
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
      <ToggleControl
        __nextHasNoMarginBottom
        label={__( 'Save this credentials in directory archive' )}
        checked={ saveCredentials }
        onChange={ (newValue) => {
          setSaveCredentials( newValue );
        } }
      />
      <Button variant="primary" onClick={() => do_login()}>Show directory</Button>
    </>
  )
}
