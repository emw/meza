#!/usr/bin/env python
#
# meza command
#
# FIXME: get commented out notes from meza.sh and make sure documented

import sys, getopt, os


def load_yaml ( filepath ):
	import yaml
	with open(filepath, 'r') as stream:
		try:
			return yaml.load(stream)
		except yaml.YAMLError as exc:
			print(exc)


paths = load_yaml( "/opt/meza/config/core/defaults.yml" )

# Hard-coded for now, because I'm not sure where to set it yet
language = "en"
i18n = load_yaml( os.path.join( paths['m_i18n'], language+".yml" ) )


def main (argv):

	# meza requires a command parameter. No first param, no command. Display
	# help. Also display help if explicitly specifying help.
	if len(argv) == 0:
		display_docs('base')
		sys.exit(1)
	elif argv[0] in ('-h', '--help'):
		display_docs('base')
		sys.exit(0) # asking for help doesn't give error code
	elif argv[0] in ('-v', '--version'):
		import subprocess
		version = subprocess.check_output( ["git", "--git-dir=/opt/meza/.git", "describe", "--tags" ] )
		commit = subprocess.check_output( ["git", "--git-dir=/opt/meza/.git", "rev-parse", "HEAD" ] )
		print "Meza " + version.strip()
		print "Commit " + commit.strip()
		print "Mediawiki EZ Admin"
		print
		sys.exit(0)


	# Every command has a sub-command. No second param, no sub-command. Display
	# help for that specific sub-command.
	if len(argv) == 1:
		display_docs(argv[0])
		sys.exit(1)
	elif len(argv) == 2 and argv[1] in ('--help','-h'):
		display_docs(argv[0])
		sys.exit(0)


	command = argv[0]
	command_fn = "meza_command_{}".format( argv[0] )

	# if command_fn is a valid Python function, pass it all remaining args
	if command_fn in globals() and callable( globals()[command_fn] ):
		globals()[command_fn]( argv[1:] )
	else:
		print
		print "{} is not a valid command".format(command)
		sys.exit(1)


def meza_command_deploy (argv):

	env = argv[0]

	rc = check_environment(env)

	# return code != 0 means failure
	if rc != 0:
		if env == "monolith":
			meza_command_setup_env(env, True)
		else:
			sys.exit(rc)

	more_extra_vars = False

	# strip environment off of it
	argv = argv[1:]

	# if argv[1:] includes -o or --overwrite
	if len( set(argv).intersection({"-o", "--overwrite"}) ) > 0:
		# remove -o and --overwrite from args;
		argv = [value for value in argv[:] if value not in ["-o", "--overwrite"]]

		more_extra_vars = { 'force_overwrite_from_backup': True }

	# This breaks continuous integration. FIXME to get it back.
	# THIS WAS WRITTEN WHEN `meza` WAS A BASH SCRIPT
	# echo "You are about to deploy to the $ansible_env environment"
	# read -p "Do you want to proceed? " -n 1 -r
	# if [[ $REPLY =~ ^[Yy]$ ]]; then
		# do dangerous stuff

		# stuff below was in here
	# fi

	shell_cmd = playbook_cmd( 'site', env, more_extra_vars )
	if len(argv) > 0:
		shell_cmd = shell_cmd + argv

	return_code = meza_shell_exec( shell_cmd )

	# exit with same return code as ansible command
	sys.exit(return_code)



# env
# dev
# dev-networking --> vbox-networking ??
# docker
def meza_command_setup (argv):

	sub_command = argv[0]
	if sub_command == "dev-networking":
		sub_command = "dev_networking" # hyphen not a valid function character
	command_fn = "meza_command_setup_" + sub_command

	# if command_fn is a valid Python function, pass it all remaining args
	if command_fn in globals() and callable( globals()[command_fn] ):
		globals()[command_fn]( argv[1:] )
	else:
		print
		print sub_command + " is not a valid sub-command for setup"
		sys.exit(1)

# FIXME: This function is big.
def meza_command_setup_env (argv, return_not_exit=False):

	import json, string

	if isinstance( argv, basestring ):
		env = argv
	else:
		env = argv[0]

	if not os.path.isdir( "/opt/conf-meza" ):
		os.mkdir( "/opt/conf-meza" )

	if not os.path.isdir( "/opt/conf-meza/secret" ):
		os.mkdir( "/opt/conf-meza/secret" )

	if os.path.isdir( "/opt/conf-meza/secret/" + env ):
		print
		print "Environment {} already exists".format(env)
		sys.exit(1)

	fqdn = db_pass = private_net_zone = False
	try:
		opts, args = getopt.getopt(argv[1:],"",["fqdn=","db_pass=","private_net_zone="])
	except Exception as e:
		print str(e)
		print 'meza setup env <env> [options]'
		sys.exit(1)
	for opt, arg in opts:
		if opt == "--fqdn":
			fqdn = arg
		elif opt == "--db_pass":
			# This will put the DB password on the command line, so should
			# only be done in testing cases
			db_pass = arg
		elif opt == "--private_net_zone":
			private_net_zone = arg
		else:
			print "Unrecognized option " + opt
			sys.exit(1)

	if not fqdn:
		fqdn = prompt("fqdn")

	if not db_pass:
		db_pass = prompt_secure("db_pass")

	# No need for private networking. Set to public.
	if env == "monolith":
		private_net_zone = "public"
	elif not private_net_zone:
		private_net_zone = prompt("private_net_zone")

	# Ansible environment variables
	env_vars = {
		'env': env,

		'fqdn': fqdn,
		'private_net_zone': private_net_zone,

		# Set all db passwords the same
		'mysql_root_pass': db_pass,
		'wiki_app_db_pass': db_pass,
		'db_slave_pass': db_pass,

		# Generate a random secret key
		'wg_secret_key': random_string( num_chars=64, valid_chars= string.ascii_letters + string.digits )

	}


	server_types = ['load_balancers','app_servers','memcached_servers',
		'db_slaves','parsoid_servers','elastic_servers','backup_servers','logging_servers']


	for stype in server_types:
		if stype in os.environ:
			env_vars[stype] = [x.strip() for x in os.environ[stype].split(',')]
		elif stype == "db_slaves":
			# unless db_slaves are explicitly set, don't configure any
			env_vars["db_slaves"] = []
		elif "default_servers" in os.environ:
			env_vars[stype] = [x.strip() for x in os.environ["default_servers"].split(',')]
		else:
			env_vars[stype] = ['localhost']


	if "db_master" in os.environ:
		env_vars["db_master"] = os.environ["db_master"].strip()
	elif "default_servers" in os.environ:
		env_vars["db_master"] = os.environ["default_servers"].strip()
	else:
		env_vars["db_master"] = 'localhost'

	json_env_vars = json.dumps(env_vars)

	# Create temporary extra vars file in secret directory so passwords
	# are not written to command line. Putting in secret should make
	# permissions acceptable since this dir will hold secret info, though it's
	# sort of an odd place for a temporary file. Perhaps /root instead?
	extra_vars_file = "/opt/conf-meza/secret/temp_vars.json"
	if os.path.isfile(extra_vars_file):
		os.remove(extra_vars_file)
	f = open(extra_vars_file, 'w')
	f.write(json_env_vars)
	f.close()

	shell_cmd = playbook_cmd( "setup-env" ) + ["--extra-vars", '@'+extra_vars_file]
	rc = meza_shell_exec( shell_cmd )

	os.remove(extra_vars_file)

	# Now that the env is setup, generate a vault password file and use it to
	# encrypt all.yml
	vault_pass_file = get_vault_pass_file( env )
	all_yml = "/opt/conf-meza/secret/{}/group_vars/all.yml".format(env)
	cmd = "ansible-vault encrypt {} --vault-password-file {}".format(all_yml, vault_pass_file)
	os.system(cmd)



	print
	print "Please review your config files. Run commands:"
	print "  sudo vi /opt/conf-meza/secret/{}/hosts".format(env)
	print "  sudo vi /opt/conf-meza/secret/{}/group_vars/all.yml".format(env)

	if return_not_exit:
		return rc
	else:
		sys.exit(rc)

def meza_command_setup_dev (argv):

	dev_users          = prompt("dev_users")
	dev_git_user       = prompt("dev_git_user")
	dev_git_user_email = prompt("dev_git_user_email")

	for dev_user in dev_users.split(' '):
		os.system( "sudo -u {} git config --global user.name '{}'".format( dev_user, dev_git_user ) )
		os.system( "sudo -u {} git config --global user.email {}".format( dev_user, dev_git_user_email ) )
		os.system( "sudo -u {} git config --global color.ui true".format( dev_user ) )

	# ref: https://www.liquidweb.com/kb/how-to-install-and-configure-vsftpd-on-centos-7/
	os.system( "yum -y install vsftpd" )
	os.system( "sed -r -i 's/anonymous_enable=YES/anonymous_enable=NO/g;' /etc/vsftpd/vsftpd.conf" )
	os.system( "sed -r -i 's/local_enable=NO/local_enable=YES/g;' /etc/vsftpd/vsftpd.conf" )
	os.system( "sed -r -i 's/write_enable=NO/write_enable=YES/g;' /etc/vsftpd/vsftpd.conf" )

	# Start FTP and setup firewall
	os.system( "systemctl restart vsftpd" )
	os.system( "systemctl enable vsftpd" )
	os.system( "firewall-cmd --permanent --add-port=21/tcp" )
	os.system( "firewall-cmd --reload" )

	print "To setup SFTP in Sublime Text, see:"
	print "https://wbond.net/sublime_packages/sftp/settings#Remote_Server_Settings"
	sys.exit()


def meza_command_setup_dev_networking (argv):
	rc = meza_shell_exec(["bash","/opt/meza/src/scripts/dev-networking.sh"])
	sys.exit(rc)

def meza_command_setup_docker (argv):
	shell_cmd = playbook_cmd( "getdocker" )
	rc = meza_shell_exec( shell_cmd )
	sys.exit(0)

def meza_command_create (argv):

	sub_command = argv[0]

	if sub_command in ("wiki", "wiki-promptless"):

		if len(argv) < 2:
			print "You must specify an environment: 'meza create wiki ENV'"
			sys.exit(1)

		env = argv[1]

		rc = check_environment(env)
		if rc > 0:
			sys.exit(rc)

		playbook = "create-" + sub_command

		if sub_command == "wiki-promptless":
			if len(argv) < 4:
				print "create wiki-promptless requires wiki_id and wiki_name arguments"
				sys.exit(1)
			shell_cmd = playbook_cmd( playbook, env, { 'wiki_id': argv[2], 'wiki_name': argv[3] } )
		else:
			shell_cmd = playbook_cmd( playbook, env )

		rc = meza_shell_exec( shell_cmd )
		sys.exit(rc)

def meza_command_backup (argv):

	env = argv[0]

	rc = check_environment(env)
	if rc != 0:
		sys.exit(rc)

	shell_cmd = playbook_cmd( 'backup', env ) + argv[1:]
	rc = meza_shell_exec( shell_cmd )

	sys.exit(rc)


def meza_command_destroy (argv):
	print "command not yet built"


def meza_command_update (argv):
	print "command not yet built"

# FIXME: It would be great to have this function automatically map all scripts
# in MediaWiki's maintenance directory to all wikis. Then you could do:
#   $ meza maint runJobs + argv            --> run jobs on all wikis
#   $ meza maint createAndPromote + argv   --> create a user on all wikis
def meza_command_maint (argv):

	# FIXME: This has no notion of environments

	sub_command = argv[0]
	command_fn = "meza_command_maint_" + sub_command

	# if command_fn is a valid Python function, pass it all remaining args
	if command_fn in globals() and callable( globals()[command_fn] ):
		globals()[command_fn]( argv[1:] )
	else:
		print
		print sub_command + " is not a valid sub-command for maint"
		sys.exit(1)



def meza_command_maint_runJobs (argv):

	#
	# WARNING: THIS FUNCTION SHOULD STILL WORK ON MONOLITHS, BUT HAS NOT BE
	#          RE-TESTED SINCE MOVING TO ANSIBLE. FOR NON-MONOLITHS IT WILL
	#          NOT WORK AND NEEDS TO BE ANSIBLE-IZED. FIXME.
	#

	wikis_dir = "/opt/htdocs/wikis"
	wikis = os.listdir( wikis_dir )
	for i in wikis:
		if os.path.isdir(os.path.join(wikis_dir, i)):
			anywiki=i
			break

	if not anywiki:
		print "No wikis available to run jobs"
		sys.exit(1)

	shell_cmd = ["WIKI="+anywiki, "php", "/opt/meza/src/scripts/runAllJobs.php"]
	if len(argv) > 0:
		shell_cmd = shell_cmd + ["--wikis="+argv[1]]
	rc = meza_shell_exec( shell_cmd )

	sys.exit(rc)

def meza_command_maint_rebuild (argv):

	env = argv[0]

	rc = check_environment(env)

	# return code != 0 means failure
	if rc != 0:
		sys.exit(rc)

	more_extra_vars = False

	# strip environment off of it
	argv = argv[1:]

	shell_cmd = playbook_cmd( 'rebuild-smw-and-index', env, more_extra_vars )
	if len(argv) > 0:
		shell_cmd = shell_cmd + argv

	return_code = meza_shell_exec( shell_cmd )

	# exit with same return code as ansible command
	sys.exit(return_code)


def meza_command_docker (argv):

	if argv[0] == "run":

		if len(argv) == 1:
			docker_repo = "jamesmontalvo3/meza-docker-test-max:latest"
		else:
			docker_repo = argv[1]

		rc = meza_shell_exec([ "bash", "/opt/meza/src/scripts/build-docker-container.sh", docker_repo])
		sys.exit(rc)


	elif argv[0] == "exec":

		if len(argv) < 2:
			print "Please provide docker container id"
			meza_shell_exec(["docker", "ps" ])
			sys.exit(1)
		else:
			container_id = argv[1]

		if len(argv) < 3:
			print "Please supply a command for your container"
			sys.exit(1)

		shell_cmd = ["docker","exec","--tty",container_id,"env","TERM=xterm"] + argv[2:]
		rc = meza_shell_exec( shell_cmd )

	else:
		print argv[0] + " is not a valid command"
		sys.exit(1)



def playbook_cmd ( playbook, env=False, more_extra_vars=False ):
	command = ['sudo', '-u', 'meza-ansible', 'ansible-playbook',
		'/opt/meza/src/playbooks/{}.yml'.format(playbook)]
	if env:
		host_file = "/opt/conf-meza/secret/{}/hosts".format(env)

		# Meza _needs_ to be able to load this file. Be perhaps a little
		# overzealous and chown/chmod it everytime
		secret_file = '/opt/conf-meza/secret/{}/group_vars/all.yml'.format(env)
		meza_chown( secret_file, 'meza-ansible', 'wheel' )
		os.chmod( secret_file, 0o660 )

		# Setup password file if not exists (environment info is encrypted)
		vault_pass_file = get_vault_pass_file( env )

		command = command + [ '-i', host_file, '--vault-password-file', vault_pass_file ]
		extra_vars = { 'env': env }

	else:
		extra_vars = {}

	if more_extra_vars:
		for varname, value in more_extra_vars.iteritems():
			extra_vars[varname] = value

	if len(extra_vars) > 0:
		import json
		command = command + ["--extra-vars", "'{}'".format(json.dumps(extra_vars))]

	return command

# FIXME install --> setup dev-networking, setup docker, deploy monolith (special case)

def meza_shell_exec ( shell_cmd ):

	# FIXME
	# Get errors with user meza-ansible trying to write to the calling-user's
	# home directory if don't cd to a neutral location. FIXME.
	starting_wd = os.getcwd()
	os.chdir( "/opt/meza/config/core" )

	# import subprocess
	# # child = subprocess.Popen(shell_cmd, stdout=subprocess.PIPE)
	# child = subprocess.Popen(shell_cmd, shell=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)
	# if return_output:
	# 	output = child.communicate()[0]
	# else:
	# 	print child.communicate()[0]
	# rc = child.returncode

	cmd = ' '.join(shell_cmd)
	print cmd
	rc = os.system(cmd)

	# FIXME: See above
	os.chdir( starting_wd )

	return rc

def get_vault_pass_file ( env ):
	import pwd
	import grp
	vault_pass_file = '/home/meza-ansible/.vault-pass-{}.txt'.format(env)
	if not os.path.isfile( vault_pass_file ):
		with open( vault_pass_file, 'w' ) as f:
			f.write( random_string( num_chars=64 ) )
			f.close()

	# Run this everytime, since it should be fast and if meza-ansible can't
	# read this then you're stuck!
	meza_chown( vault_pass_file, 'meza-ansible', 'wheel' )
	os.chmod( vault_pass_file, 0o600 )

	return vault_pass_file

def meza_chown ( path, username, groupname ):
	import pwd
	import grp
	uid = pwd.getpwnam( username ).pw_uid
	gid = grp.getgrnam( groupname ).gr_gid
	os.chown( path, uid, gid )

def display_docs(name):
	f = open('/opt/meza/manual/meza-cmd/{}.txt'.format(name),'r')
	print f.read()

def prompt(varname,default=False):

	# Pretext message is prior to the actual line the user types on. Input msg
	# is on the same line and will be repeated if the user does not give good
	# input
	pretext_msg = i18n["MSG_prompt_pretext_"+varname]
	input_msg = i18n["MSG_prompt_input_"+varname]

	print
	print pretext_msg

	value = raw_input( input_msg )
	if default:
		# If there's a default, either use user entry or default
		value = value or default
	else:
		# If no default, keep asking until user supplies a value
		while (not value):
			value = raw_input( input_msg )

	return value

def prompt_secure(varname):
	import getpass

	# See prompt() for more info
	pretext_msg = i18n["MSG_prompt_pretext_"+varname]
	input_msg = i18n["MSG_prompt_input_"+varname]

	print
	print pretext_msg

	value = getpass.getpass( input_msg )
	if not value:
		value = random_string()

	return value

def random_string(**params):
	import string, random

	if 'num_chars' in params:
		num_chars = params['num_chars']
	else:
		num_chars = 32

	if 'valid_chars' in params:
		valid_chars = params['valid_chars']
	else:
		valid_chars = string.ascii_letters + string.digits + '!@$%^*'

	return ''.join(random.SystemRandom().choice(valid_chars) for _ in range(num_chars))


# return code 0 success, 1+ failure
def check_environment(env):
	import os

	conf_dir = "/opt/conf-meza/secret"

	env_dir = os.path.join( conf_dir, env )
	if not os.path.isdir( env_dir ):

		if env == "monolith":
			return 1

		print
		print '"{}" is not a valid environment.'.format(env)
		print "Please choose one of the following:"

		conf_dir_stuff = os.listdir( conf_dir )
		valid_envs = []
		for x in conf_dir_stuff:
			if os.path.isdir( os.path.join( conf_dir, x ) ):
				valid_envs.append( x )

		if len(valid_envs) > 0:
			for x in valid_envs:
				print "  " + x
		else:
			print "  No environments configured"
			print "  Run command: meza setup env <environment name>"

		return 1

	host_file = os.path.join( env_dir, "hosts" )
	if not os.path.isfile( host_file ):
		print
		print "{} not a valid file".format( host_file )
		return 1

	return 0

# http://stackoverflow.com/questions/1994488/copy-file-or-directories-recursively-in-python
def copy (src, dst):
	import shutil, errno

	try:
		shutil.copytree(src, dst)
	except OSError as exc: # python >2.5
		if exc.errno == errno.ENOTDIR:
			shutil.copy(src, dst)
		else: raise


if __name__ == "__main__":
	main(sys.argv[1:])

