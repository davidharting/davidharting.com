import { BaseCommand, flags } from '@adonisjs/core/build/standalone'

export default class PipelineDeploy extends BaseCommand {
  /**
   * Command name is used to run the command
   */
  public static commandName = 'pipeline:deploy'

  /**
   * Command description is displayed in the "help" output
   */
  public static description = ''

  @flags.string()
  public flyAccessToken: string

  public static settings = {
    /**
     * Set the following value to true, if you want to load the application
     * before running the command. Don't forget to call `node ace generate:manifest`
     * afterwards.
     */
    loadApp: false,

    /**
     * Set the following value to true, if you want this command to keep running until
     * you manually decide to exit the process. Don't forget to call
     * `node ace generate:manifest` afterwards.
     */
    stayAlive: false,
  }

  public async run() {
    this.logger.info('Starting dagger deploy pipeline.')
    // eslint-disable-next-line no-eval
    const dagger = await (eval(`import('@dagger.io/dagger')`) as Promise<
      typeof import('@dagger.io/dagger')
    >)

    await dagger.connect(
      async (client) => {
        const out = await client
          .container()
          .from('flyio/flyctl:latest')
          .withMountedDirectory(
            '/repository',
            client.host().directory('.', {
              exclude: [
                'node_modules/',
                'build/',
                '.vscode/',
                '.env.test',
                '.env.ci',
                '.env.example',
              ],
            })
          )
          .withWorkdir('/repository')
          .withEnvVariable(
            'FLY_ACCESS_TOKEN',
            this.flyAccessToken || process.env.FLY_ACCESS_TOKEN || ''
          )
          .withExec(['deploy'])
          .stderr()
        console.error(out)
      },
      { LogOutput: process.stdout }
    )

    this.logger.info('Finished dagger deploy pipeline.')
  }
}
