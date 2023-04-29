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
    this.logger.info('Starting dagger pipeline.')
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
        const repository = client
          .container()
          .from('node:19.8-bullseye')
          .withMountedDirectory(
            './',
            client
              .host()
              .directory('/repository', { exclude: ['node_modules/', 'build/', '.vscode/'] })
          )

        const runner = repository.withWorkdir('/repository').withExec(['npm', 'install']) // Why do I have to call withWorkDir if I am calling exec on the repository itself?
        const out = await runner.withExec(['node', 'ace', 'test']).stderr()
        console.log(out) // Does the `.stderr` not actually get anything out? Why would I not want this to go to stdout and automatically be logged?
      },
      { LogOutput: process.stdout }
    )
    this.logger.info('Dagger pipeline has finished.')
  }
}
