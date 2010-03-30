from mod_python import apache

def handler(req):
	req.write("Hello World!")
	return apache.OK
