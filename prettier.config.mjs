/** @type {import('prettier').Config} */
export default {
  plugins: ['prettier-plugin-sql'],
  semi: false,
  singleQuote: true,
  trailingComma: 'all',
  printWidth: 100,
  tabWidth: 2,
  vueIndentScriptAndStyle: false,
  language: 'postgresql',
  keywordCase: 'upper',
  dataTypeCase: 'lower',
  functionCase: 'lower',
  linesBetweenQueries: 1,
}
