/**
 * Import dependencies.
 */
const { _n } = wp.i18n;

/**
 * Show list of errors.
 *
 * @param errors
 * @constructor
 */
export const EDLFW_ERRORS = ( { errors } ) => {
  return (
    <>
      <div className="errors">
        <p><strong>{errors.length === 1 && edlfwJsVars.error_title}{errors.length > 1 && edlfwJsVars.errors_title}</strong></p>
        <ul>
        {errors.map( error => {
          return <li>{error}</li>
        })
        }
        </ul>
      </div>
    </>
  )
}
