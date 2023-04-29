import { BaseCommand } from '@adonisjs/core/build/standalone'

export default class PipelineCi extends BaseCommand {
  /**
   * Command name is used to run the command
   */
  public static commandName = 'pipeline:ci'

  /**
   * Command description is displayed in the "help" output
   */
  public static description = 'Run the CI pipeline using Dagger'

  public static settings = {
    /**
     * Set the following value to true, if you want to load the application
     * before running the command.
     */
    loadApp: false,

    /**
     * Set the following value to true, if you want this command to keep running until
     * you manually decide to exit the process.
     */
    stayAlive: false,
  }

  public async run() {
    // Adonisjs ace commands only support common JS.
    // TypeScript refuses to keep dynamic imports and always compiels them down to require when targeting commonjs.
    // Because of this, we must use eval to get the esm version of dagger via dynamic import.
    // It's nasty, but it works.
    // https://stackoverflow.com/questions/70545129/compile-a-package-that-depends-on-esm-only-library-into-a-commonjs-package
    // eslint-disable-next-line no-eval
    const dagger = await (eval(`import('@dagger.io/dagger')`) as Promise<
      typeof import('@dagger.io/dagger')
    >)

    console.log(dagger.connect)
    await dagger.connect(
      async (client) => {
        // get version
        const node = client.container().from('node:19.8-bullseye').withExec(['node', '-v'])

        // execute
        const version = await node.stdout()

        // print output
        console.log('Hello from Dagger and Node ' + version)
      },
      { LogOutput: process.stdout }
    )
    this.logger.info('Hello world!')
  }
}
