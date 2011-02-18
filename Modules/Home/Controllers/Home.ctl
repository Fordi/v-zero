<Controller>
	<Action id="Start" title="Displays the Home page">
	
		<Site:GenericComponent attribute="value" />
		
		<AnotherModule:ComponentWithError title="Content of a Component call executed on error">
			<Jump action="AnotherController/EntryID" />
		</AnotherModule:ComponentWithError>
		
		<Call action="{%$__CONTROLLER%}/End" title="This one goes to the current controller" />
		
		<Do if="{%$phpExpression%}">
			<Jump action="AnotherController/AnotherEntryID" />
		</Do>
		
		<Do while="{%weShouldKeepGoing()%}">
			<Call action="AnotherController/AnotherEntryID" />
			<Call action="AnotherController/AnotherEntryID" />
			<Site:DoStuff />
		</Do>
		
		<iterate over="{%$thingy%}" using="varName">
			<Do if="{%$varName%}">
				
			</Do>
		</iterate>
		
		<iterate to="10" using="pageNumber">
			<Pagination:PreparePage pageNo="{%$pageNumber%}" />
			<Break />
			<Continue />
		</iterate>
		
		<Site:MoreComponent>
			<Return />
		</Site:MoreComponent>
		
		<Return template="AnAwesomeTemplate" />
		
	</Action>
	
</Controller>